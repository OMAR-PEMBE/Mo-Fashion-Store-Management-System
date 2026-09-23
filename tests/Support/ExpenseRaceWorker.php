<?php

use App\Models\Expense;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
[$script, $actorId, $categoryId, $key, $directory, $worker, $expenseId] = $argv;
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
    $actor = User::findOrFail($actorId);
    $expense = $expenseId ? Expense::findOrFail($expenseId) : null;
    file_put_contents($resolved.'/'.$worker.'.attempting', 'attempting');
    $result = app(ExpenseService::class)->save(['expense_category_id' => $categoryId, 'amount' => $expense ? ($worker === 'a' ? '20.30' : '30.40') : '10.25',
        'expense_date' => '2026-09-23', 'description' => 'Concurrency test', 'request_key' => $key, 'revision' => 1], $actor, $expense);
    echo json_encode(['status' => 'success', 'id' => $result->id]);
} catch (HttpException $error) {
    echo json_encode(['status' => 'rejected', 'code' => $error->getStatusCode()]);
} catch (Throwable $error) {
    echo json_encode(['status' => 'error', 'class' => $error::class]);
    exit(1);
}
