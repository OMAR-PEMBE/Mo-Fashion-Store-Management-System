<?php

namespace Tests\MySql;

use App\Enums\InventoryMovementType as Type;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\ProductCatalogueService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrderConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [
            'two orders final stock' => ['orderconfirm', 1, 1, false, 1, 1, 1],
            'duplicate confirmation' => ['orderconfirm', 1, 1, true, 1, 1, 1],
            'duplicate conversion' => ['orderconvert', 1, 1, true, 0, 0, 1],
            'duplicate cancellation' => ['ordercancel', 1, 1, true, 1, 0, 1],
        ];
    }

    #[DataProvider('races')]
    public function test_real_mysql_writers_serialize_safely(string $mode, int $initial, int $quantity, bool $sameKey, int $physical, int $reserved, int $successes): void
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
            if ($initial) {
                app(InventoryService::class)->increase($variant, $initial, Type::OpeningBalance, new InventoryContext($actor, $token.':initial', 'concurrency_test', 1));
            }
            $customer = app(CustomerService::class)->save(['full_name' => 'Concurrency customer '.$token], $actor);
            $orders = [];
            foreach (['a', 'b'] as $worker) {
                if ($worker === 'b' && $sameKey) {
                    $orders[$worker] = $orders['a'];

                    continue;
                }
                $orders[$worker] = app(OrderService::class)->create(['request_key' => $token.':'.$worker, 'customer_id' => $customer->id,
                    'items' => [['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '100']]], $actor);
            }
            if ($mode !== 'orderconfirm') {
                app(OrderService::class)->confirm($orders['a'], $actor);
                if ($mode === 'orderconvert') {
                    app(OrderService::class)->markPaid($orders['a'], ['payment_method' => 'CASH'], $actor);
                }
            }
            // Fixtures are committed so independent worker connections can see them.
            DB::beginTransaction();
            DB::table('products')->where('id', $product->id)->lockForUpdate()->first();
            foreach (['a', 'b'] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/InventoryRaceWorker.php'), (string) $variant->id,
                    (string) $actor->id, $token.':'.($sameKey ? 'same' : $worker), $directory, $worker, $mode, (string) $orders[$worker]->id], base_path());
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
            $this->assertCount(1, array_filter($results, fn ($result) => $result['status'] === 'rejected'));
            if ($sameKey) {
                $this->assertCount(1, array_filter($results, fn ($result) => ($result['code'] ?? null) === 409));
            }
            $balance = Inventory::where('product_variant_id', $variant->id)->firstOrFail();
            $this->assertSame($physical, $balance->physical_quantity);
            $this->assertSame($reserved, $balance->reserved_quantity);
            $this->assertSame($physical - $reserved, $balance->available_quantity);
            $this->assertSame(($mode === 'orderconfirm' ? 2 : 3), $variant->movements()->count());
            try {
                DB::table('inventories')->where('id', $balance->id)->update(['reserved_quantity' => $physical + 1]);
                $this->fail('MySQL must reject reserved stock exceeding physical stock.');
            } catch (QueryException $exception) {
                $this->assertSame(3819, $exception->errorInfo[1]);
            }
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
                DB::table('audit_logs')->where('entity_type', 'sale')->whereIn('entity_id', $saleIds)->delete();
                DB::table('sale_items')->whereIn('sale_id', $saleIds)->delete();
                DB::table('sales')->whereIn('id', $saleIds)->delete();
                $orderIds = DB::table('order_items')->where('product_variant_id', $variant->id)->pluck('order_id');
                DB::table('audit_logs')->where('entity_type', 'order')->whereIn('entity_id', $orderIds)->delete();
                DB::table('stock_reservations')->whereIn('order_id', $orderIds)->delete();
                DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();
                if (isset($customer)) {
                    DB::table('audit_logs')->where('entity_type', 'customer')->where('entity_id', $customer->id)->delete();
                    DB::table('customers')->where('id', $customer->id)->delete();
                }
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
