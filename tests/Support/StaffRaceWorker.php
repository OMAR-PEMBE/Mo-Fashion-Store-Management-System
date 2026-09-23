<?php

use App\Models\User;
use App\Services\StaffService;
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
[$script, $actorId, $targetId, $directory, $worker] = $argv;
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
    $target = User::findOrFail($targetId);
    file_put_contents($resolved.'/'.$worker.'.attempting', 'attempting');
    $result = app(StaffService::class)->save(['name' => $target->name, 'email' => $target->email,
        'role_id' => $target->role_id, 'is_active' => false, 'revision' => 1, 'current_password' => 'password'], $actor, $target);
    echo json_encode(['status' => 'success', 'id' => $result->id]);
} catch (HttpException $error) {
    echo json_encode(['status' => 'rejected', 'code' => $error->getStatusCode()]);
} catch (Throwable $error) {
    echo json_encode(['status' => 'error', 'class' => $error::class]);
    exit(1);
}
