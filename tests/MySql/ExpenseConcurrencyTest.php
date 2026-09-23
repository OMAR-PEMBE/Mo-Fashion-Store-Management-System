<?php

namespace Tests\MySql;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ExpenseConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return ['duplicate creation' => [false], 'competing corrections' => [true]];
    }

    #[DataProvider('races')]
    public function test_simultaneous_writers_preserve_one_record_and_audit(bool $edit): void
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
        try {
            $actor = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $category = ExpenseCategory::create(['name' => $token, 'is_active' => true]);
            $expense = $edit ? app(ExpenseService::class)->save(['expense_category_id' => $category->id, 'amount' => '10.25', 'expense_date' => '2026-09-23', 'request_key' => $token], $actor) : null;
            DB::beginTransaction();
            DB::table('document_sequences')->where('document_type', 'EXPENSE')->lockForUpdate()->first();
            foreach (['a', 'b'] as $worker) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/ExpenseRaceWorker.php'), (string) $actor->id, (string) $category->id,
                    $token, $directory, $worker, (string) ($expense?->id ?? 0)], base_path());
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
            $this->assertCount($edit ? 1 : 2, array_filter($results, fn ($result) => $result['status'] === 'success'));
            $this->assertCount($edit ? 1 : 0, array_filter($results, fn ($result) => ($result['code'] ?? null) === 409));
            $rows = Expense::where('recorded_by', $actor->id)->get();
            $this->assertCount(1, $rows);
            $this->assertSame($edit ? 2 : 1, $rows[0]->revision);
            $this->assertContains($rows[0]->amount, $edit ? ['20.30', '30.40'] : ['10.25']);
            $this->assertSame($edit ? 2 : 1, DB::table('audit_logs')->where('entity_type', 'expense')->where('entity_id', $rows[0]->id)->count());
            // The upper DECIMAL boundary requires MySQL; SQLite uses floating-point storage.
            if (! $edit) {
                $maximum = app(ExpenseService::class)->save(['expense_category_id' => $category->id, 'amount' => '9999999999999.99',
                    'expense_date' => '2026-09-23', 'request_key' => $token.':max'], $actor);
                $this->assertSame('9999999999999.99', $maximum->fresh()->amount);
                $audit = DB::table('audit_logs')->where('entity_type', 'expense')->where('entity_id', $maximum->id)->first();
                $this->assertSame('9999999999999.99', json_decode($audit->new_values, true)['amount']);
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
            if ($actor) {
                $ids = DB::table('expenses')->where('recorded_by', $actor->id)->pluck('id');
                DB::table('audit_logs')->where('entity_type', 'expense')->whereIn('entity_id', $ids)->delete();
                DB::table('expenses')->whereIn('id', $ids)->delete();
                DB::table('users')->where('id', $actor->id)->delete();
            }
            if ($category) {
                DB::table('expense_categories')->where('id', $category->id)->delete();
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
        $this->fail('Expense worker did not reach the synchronization barrier.');
    }
}
