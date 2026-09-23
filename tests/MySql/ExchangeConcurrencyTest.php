<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\ExchangeService;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\RefundService;
use App\Services\ReturnService;
use App\Services\SaleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ExchangeConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [
            'duplicate exchange' => ['duplicate', 1],
            'competing exchanges' => ['exchange', 1],
            'exchange versus return' => ['return', 1],
            'exchange versus refund approval' => ['refund', 1],
        ];
    }

    #[DataProvider('races')]
    public function test_real_mysql_writers_serialize_safely(string $mode, int $successes): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(DatabaseSeeder::class);
        $token = (string) Str::uuid();
        $directory = storage_path('framework/testing/'.$token);
        mkdir($directory, 0777, true);
        $processes = [];
        $actor = null;
        $category = null;
        $product = null;
        $variant = null;
        try {
            $actor = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $category = Category::create(['name' => 'Concurrency test', 'slug' => $token]);
            $product = app(ProductCatalogueService::class)->saveProduct(['name' => 'Concurrency test', 'product_code' => $token,
                'category_id' => $category->id, 'is_active' => 1], $actor);
            $variant = app(ProductCatalogueService::class)->saveVariant($product, ['sku' => $token, 'selling_price' => '100', 'low_stock_threshold' => 2, 'is_active' => 1]);
            app(OpeningStockService::class)->confirm($variant, ['quantity' => 1, 'unit_cost' => '25'], $actor);
            $sale = app(SaleService::class)->completeSale(['request_key' => $token.':sale', 'payment_method' => 'CASH',
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '100']]], $actor);
            $exchangeService = app(ExchangeService::class);
            $data = ['sale_id' => $sale->id, 'request_key' => $token.':exchange', 'reason' => 'Test',
                'returned_items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1, 'condition' => 'SELLABLE']],
                'replacement_items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]];
            $first = $exchangeService->create($data, $actor);
            $secondId = $first->id;
            $secondMode = 'exchangecomplete';
            if ($mode === 'exchange') {
                $data['request_key'] = $token.':second';
                $secondId = $exchangeService->create($data, $actor)->id;
            }
            if ($mode === 'return') {
                $returnService = app(ReturnService::class);
                $return = $returnService->create(['sale_id' => $sale->id, 'request_key' => $token.':return', 'reason' => 'Test', 'proof_type' => 'SALE_RECORD',
                    'items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1, 'condition' => 'SELLABLE']]], $actor);
                $returnService->approve($return, $actor);
                $secondId = $return->id;
                $secondMode = 'returncomplete';
            }
            if ($mode === 'refund') {
                $refund = app(RefundService::class)->create(['sale_id' => $sale->id, 'request_key' => $token.':refund', 'reason' => 'Test',
                    'items' => [['sale_item_id' => $sale->items()->first()->id, 'amount' => '100.00']]], $actor);
                $secondId = $refund->id;
                $secondMode = 'refundapprove';
            }
            // Fixtures are committed so independent worker connections can see them.
            DB::beginTransaction();
            DB::table('sales')->where('id', $sale->id)->lockForUpdate()->first();
            foreach (['a', 'b'] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/InventoryRaceWorker.php'), (string) $variant->id,
                    (string) $actor->id, $token.':'.$worker, $directory, $worker, ($worker === 'a' ? 'exchangecomplete' : $secondMode), (string) ($worker === 'a' ? $first->id : $secondId)], base_path());
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $this->waitForFiles($directory, ['a.ready', 'b.ready']);
            touch($directory.'/go');
            $this->waitForFiles($directory, ['a.attempting', 'b.attempting']);
            usleep(150000);
            $this->assertTrue($processes[0]->isRunning());
            $this->assertTrue($processes[1]->isRunning());
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getOutput().$process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertCount($successes, array_filter($results, fn ($result) => $result['status'] === 'success'));
            $this->assertCount(2 - $successes, array_filter($results, fn ($result) => $result['status'] === 'rejected'));
            if ($mode === 'duplicate') {
                $this->assertCount(1, array_filter($results, fn ($result) => ($result['code'] ?? null) === 409));
            }
            $exchangeCompleted = DB::table('exchanges')->where('sale_id', $sale->id)->where('status', 'COMPLETED')->count();
            $returnCompleted = DB::table('returns')->where('sale_id', $sale->id)->where('status', 'COMPLETED')->count();
            $refundApproved = DB::table('refunds')->where('sale_id', $sale->id)->where('status', 'APPROVED')->count();
            $this->assertSame(1, $exchangeCompleted + $returnCompleted + $refundApproved);
            $balance = Inventory::where('product_variant_id', $variant->id)->firstOrFail();
            $this->assertSame($returnCompleted, $balance->physical_quantity);
            $this->assertSame(0, $balance->reserved_quantity);
            $this->assertSame(2 + ($exchangeCompleted * 2) + $returnCompleted, $variant->movements()->count());
            $this->assertSame('100.00', $sale->fresh()->total_amount);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            // Delete only fixture IDs from the explicitly guarded test schema.
            if ($variant) {
                $saleIds = DB::table('sale_items')->where('product_variant_id', $variant->id)->pluck('sale_id');
                $exchangeIds = DB::table('exchanges')->whereIn('sale_id', $saleIds)->pluck('id');
                DB::table('audit_logs')->where('entity_type', 'exchange')->whereIn('entity_id', $exchangeIds)->delete();
                DB::table('exchange_items')->whereIn('exchange_id', $exchangeIds)->delete();
                DB::table('exchanges')->whereIn('id', $exchangeIds)->delete();
                $refundIds = DB::table('refunds')->whereIn('sale_id', $saleIds)->pluck('id');
                DB::table('audit_logs')->where('entity_type', 'refund')->whereIn('entity_id', $refundIds)->delete();
                DB::table('refund_items')->whereIn('refund_id', $refundIds)->delete();
                DB::table('refunds')->whereIn('id', $refundIds)->delete();
                $returnIds = DB::table('returns')->whereIn('sale_id', $saleIds)->pluck('id');
                DB::table('audit_logs')->where('entity_type', 'return')->whereIn('entity_id', $returnIds)->delete();
                DB::table('return_items')->whereIn('return_id', $returnIds)->delete();
                DB::table('returns')->whereIn('id', $returnIds)->delete();
                DB::table('audit_logs')->where('entity_type', 'sale')->whereIn('entity_id', $saleIds)->delete();
                DB::table('sale_items')->whereIn('sale_id', $saleIds)->delete();
                DB::table('sales')->whereIn('id', $saleIds)->delete();
                DB::table('audit_logs')->where('entity_type', 'product_variant')->where('entity_id', $variant->id)->delete();
                DB::table('inventory_movements')->where('product_variant_id', $variant->id)->delete();
                DB::table('inventories')->where('product_variant_id', $variant->id)->delete();
                DB::table('product_variants')->where('id', $variant->id)->delete();
            }
            if ($product) {
                DB::table('products')->where('id', $product->id)->delete();
            }
            if ($category) {
                DB::table('categories')->where('id', $category->id)->delete();
            }
            if ($actor) {
                DB::table('users')->where('id', $actor->id)->delete();
            }
            foreach (['a.ready', 'b.ready', 'a.attempting', 'b.attempting', 'go'] as $file) {
                if (is_file($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                }
            }
            rmdir($directory);
        }
    }

    private function waitForFiles(string $directory, array $files): void
    {
        $deadline = microtime(true) + 15;
        do {
            clearstatcache();
            if (count(array_filter($files, fn ($file) => is_file($directory.'/'.$file))) === count($files)) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Inventory worker did not reach the synchronization barrier.');
    }
}
