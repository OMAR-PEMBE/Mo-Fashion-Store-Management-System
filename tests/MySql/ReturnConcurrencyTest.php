<?php

namespace Tests\MySql;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\OpeningStockService;
use App\Services\ProductCatalogueService;
use App\Services\ReturnService;
use App\Services\SaleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReturnConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [
            'competing returns' => ['different', 1],
            'duplicate completion' => ['duplicate', 1],
            'return versus counter sale' => ['counter', 2],
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
            app(OpeningStockService::class)->confirm($variant, ['quantity' => $mode === 'counter' ? 2 : 1, 'unit_cost' => '25'], $actor);
            $sale = app(SaleService::class)->completeSale(['request_key' => $token.':sale', 'payment_method' => 'CASH',
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '100']]], $actor);
            $returns = [];
            foreach (['a', 'b'] as $worker) {
                if ($worker === 'b' && $mode !== 'different') {
                    $returns[$worker] = $returns['a'];

                    continue;
                }
                $returns[$worker] = app(ReturnService::class)->create(['request_key' => $token.':'.$worker, 'sale_id' => $sale->id, 'reason' => 'Test return', 'proof_type' => 'SALE_RECORD',
                    'items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1, 'condition' => 'SELLABLE']]], $actor);
                app(ReturnService::class)->approve($returns[$worker], $actor);
            }
            // Fixtures are committed so independent worker connections can see them.
            DB::beginTransaction();
            DB::table('products')->where('id', $product->id)->lockForUpdate()->first();
            foreach (['a', 'b'] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/InventoryRaceWorker.php'), (string) $variant->id,
                    (string) $actor->id, $token.':'.$worker, $directory, $worker, ($mode === 'counter' && $worker === 'b' ? 'counter' : 'returncomplete'), ($mode === 'counter' && $worker === 'b' ? '1' : (string) $returns[$worker]->id)], base_path());
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
            $balance = Inventory::where('product_variant_id', $variant->id)->firstOrFail();
            $this->assertSame(1, $balance->physical_quantity);
            $this->assertSame(0, $balance->reserved_quantity);
            $this->assertSame($mode === 'counter' ? 4 : 3, $variant->movements()->count());
            $this->assertSame(1, DB::table('returns')->where('sale_id', $sale->id)->where('status', 'COMPLETED')->count());
            $this->assertSame(0, array_values(app(ReturnService::class)->remaining($sale))[0]);
            $this->assertSame('25.00', $variant->fresh()->weighted_average_cost);
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
