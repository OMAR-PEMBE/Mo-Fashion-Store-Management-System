<?php

use App\Enums\InventoryMovementType;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OpeningStockService;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\InventoryContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Invoked by the MySQL concurrency test, never by a web route.
require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=mfbms_testing');
putenv('DB_URL=');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || DB::connection()->getDatabaseName() !== 'mfbms_testing') {
    exit(2);
}
[$script, $variantId, $actorId, $key, $directory, $worker, $mode, $quantity] = $argv;
$root = realpath(storage_path('framework/testing'));
$resolved = realpath($directory);
if (! $root || ! $resolved || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || ! in_array($worker, ['a', 'b'])) {
    exit(3);
}
file_put_contents($resolved.'/'.$worker.'.ready', 'ready');
$deadline = microtime(true) + 20;
while (! file_exists($resolved.'/go')) {
    if (microtime(true) > $deadline) {
        exit(4);
    }
    usleep(10000);
}
try {
    $variant = ProductVariant::findOrFail($variantId);
    $actor = User::findOrFail($actorId);
    $context = new InventoryContext($actor, $key, 'concurrency_test', 1);
    $service = app(InventoryService::class);
    file_put_contents($resolved.'/'.$worker.'.attempting', 'attempting');
    if (in_array($mode, ['orderconfirm', 'orderconvert', 'ordercancel'])) {
        $orders = app(OrderService::class);
        $order = Order::findOrFail((int) $quantity);
        $result = match ($mode) {
            'orderconfirm' => $orders->confirm($order, $actor),
            'orderconvert' => $orders->convertToSale($order, $actor),
            'ordercancel' => $orders->cancel($order, $actor, 'Concurrency test'),
        };
        echo json_encode(['status' => 'success', 'movement_id' => $result->id]);
        exit(0);
    }
    if ($mode === 'counter') {
        $sale = app(SaleService::class)->completeSale(['request_key' => $key, 'payment_method' => 'CASH',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => (int) $quantity, 'unit_price' => '100.00']]], $actor);
        echo json_encode(['status' => 'success', 'movement_id' => $sale->id]);
        exit(0);
    }
    if ($mode === 'opening') {
        $movement = app(OpeningStockService::class)->confirm($variant, ['quantity' => (int) $quantity, 'unit_cost' => '25.00'], $actor);
        echo json_encode(['status' => 'success', 'movement_id' => $movement->id]);
        exit(0);
    }
    if ($mode === 'confirm') {
        $purchase = app(PurchaseService::class)->confirm(Purchase::findOrFail((int) $quantity), $actor, 1);
        echo json_encode(['status' => 'success', 'movement_id' => $purchase->id]);
        exit(0);
    }
    $movement = match ($mode) {
        'increase' => $service->increase($variant, (int) $quantity, InventoryMovementType::Purchase, $context),
        'decrease' => $service->decrease($variant, (int) $quantity, InventoryMovementType::Sale, $context),
        'reserve' => $service->reserve($variant, (int) $quantity, $context),
    };
    echo json_encode(['status' => 'success', 'movement_id' => $movement->id]);
} catch (ValidationException) {
    echo json_encode(['status' => 'rejected']);
} catch (HttpException $exception) {
    echo json_encode(['status' => 'rejected', 'code' => $exception->getStatusCode()]);
} catch (Throwable $exception) {
    echo json_encode(['status' => 'error', 'class' => $exception::class]);
    exit(1);
}
