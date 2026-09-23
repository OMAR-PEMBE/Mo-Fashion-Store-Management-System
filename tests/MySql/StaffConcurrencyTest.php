<?php

namespace Tests\MySql;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StaffConcurrencyTest extends TestCase
{
    public function test_competing_administrators_cannot_deactivate_each_other(): void
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
        $other = null;
        try {
            $actor = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $other = User::factory()->create(['role_id' => $actor->role_id]);
            DB::beginTransaction();
            DB::table('roles')->where('slug', 'administrator')->lockForUpdate()->first();
            foreach (['a', 'b'] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/StaffRaceWorker.php'),
                    (string) ($worker === 'a' ? $actor->id : $other->id),
                    (string) ($worker === 'a' ? $other->id : $actor->id), $directory, $worker], base_path());
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
            $this->assertCount(1, array_filter($results, fn ($result) => $result['status'] === 'success'));
            $this->assertCount(1, array_filter($results, fn ($result) => ($result['code'] ?? null) === 403));
            $this->assertSame(1, User::whereIn('id', [$actor->id, $other->id])->where('is_active', true)->count());
            $this->assertSame(1, DB::table('audit_logs')->where('action', 'UPDATE_STAFF')->whereIn('entity_id', [$actor->id, $other->id])->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $ids = array_filter([$actor?->id, $other?->id]);
            DB::table('audit_logs')->where('entity_type', 'user')->whereIn('entity_id', $ids)->delete();
            DB::table('users')->whereIn('id', $ids)->delete();
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
        $this->fail('Staff worker did not reach the synchronization barrier.');
    }
}
