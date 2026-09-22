<?php

namespace Tests\MySql;

use App\Enums\InventoryMovementType as Type;
use App\Models\Category;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductCatalogueService;
use App\Services\PurchaseService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PurchaseConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return ['duplicate confirmation' => ['duplicate'], 'two purchases' => ['purchases'], 'purchase and sale' => ['sale']];
    }

    #[DataProvider('races')]
    public function test_real_mysql_writers_serialize_safely(string $scenario): void
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
        $supplier = null;
        $purchases = [];
        try {
            $actor = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $category = Category::create(['name' => 'Concurrency test', 'slug' => $token]);
            $product = app(ProductCatalogueService::class)->saveProduct(['name' => 'Concurrency test', 'product_code' => $token,
                'category_id' => $category->id, 'is_active' => 1], $actor);
            $variant = app(ProductCatalogueService::class)->saveVariant($product, ['sku' => $token, 'selling_price' => '100', 'low_stock_threshold' => 2, 'is_active' => 1]);
            $supplier = Supplier::create(['name' => 'Race supplier', 'supplier_code' => $token]);
            $service = app(PurchaseService::class);
            $make = function (string $cost, int $quantity = 10) use ($service, $supplier, $variant, $actor, &$purchases) {
                $purchase = $service->createDraft(['supplier_id' => $supplier->id, 'purchase_date' => '2026-09-22', 'payment_status' => 'PAID',
                    'items' => [['product_variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost' => $cost]]], $actor);
                $purchases[] = $purchase->id;

                return $purchase;
            };
            $service->confirm($make('25000'), $actor, 1);
            $first = $make('30000');
            $second = $scenario === 'purchases' ? $make('35000') : $first;
            // Fixtures are committed so independent worker connections can see them.
            DB::beginTransaction();
            DB::table('products')->where('id', $product->id)->lockForUpdate()->first();
            foreach (['a', 'b'] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/InventoryRaceWorker.php'), (string) $variant->id,
                    (string) $actor->id, $token.':'.$worker, $directory, $worker, ($scenario === 'sale' && $worker === 'b') ? 'counter' : 'confirm', (string) (($scenario === 'sale' && $worker === 'b') ? 4 : ($worker === 'a' ? $first->id : $second->id))], base_path());
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
            $this->assertCount($scenario === 'duplicate' ? 1 : 2, array_filter($results, fn ($r) => $r['status'] === 'success'));
            if ($scenario === 'duplicate') {
                $this->assertCount(1, array_filter($results, fn ($r) => ($r['code'] ?? null) === 409));
            }
            $balance = $variant->inventory()->firstOrFail();
            $this->assertSame(match ($scenario) {
                'duplicate' => 20, 'purchases' => 30, 'sale' => 16
            }, $balance->physical_quantity);
            $this->assertSame(0, $balance->reserved_quantity);
            $expectedCost = match ($scenario) {
                'duplicate' => '27500.00', 'purchases' => '30000.00',
                'sale' => $variant->movements()->orderBy('id')->skip(1)->first()->movement_type === Type::Sale ? '28125.00' : '27500.00',
            };
            $this->assertSame($expectedCost, $variant->fresh()->weighted_average_cost);
            $this->assertSame($scenario === 'duplicate' ? 2 : 3, $variant->movements()->count());
            $this->assertSame($scenario === 'purchases' ? 3 : 2, DB::table('audit_logs')->where('entity_type', 'purchase')->whereIn('entity_id', $purchases)->count());
            // Exact DECIMAL boundary is certified on MySQL, not SQLite's numeric affinity.
            $large = $make('9999999999999.99', 1);
            $this->assertSame('9999999999999.99', $large->total_amount);
            $this->assertSame('9999999999999.99', $large->items()->first()->unit_cost);
            $beforeCost = $variant->fresh()->weighted_average_cost;
            $expected = BigDecimal::of($beforeCost)->multipliedBy($balance->physical_quantity)->plus('9999999999999.99')
                ->dividedBy($balance->physical_quantity + 1, 2, RoundingMode::HalfUp);
            $service->confirm($large, $actor, 1);
            $this->assertSame((string) $expected, $variant->fresh()->weighted_average_cost);
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
            DB::table('audit_logs')->where('entity_type', 'purchase')->whereIn('entity_id', $purchases)->delete();
            DB::table('purchase_items')->whereIn('purchase_id', $purchases)->delete();
            DB::table('purchases')->whereIn('id', $purchases)->delete();
            if ($supplier) {
                DB::table('suppliers')->where('id', $supplier->id)->delete();
            }
            if ($variant) {
                $saleIds = DB::table('sale_items')->where('product_variant_id', $variant->id)->pluck('sale_id');
                DB::table('audit_logs')->where('entity_type', 'sale')->whereIn('entity_id', $saleIds)->delete();
                DB::table('sale_items')->whereIn('sale_id', $saleIds)->delete();
                DB::table('sales')->whereIn('id', $saleIds)->delete();
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
