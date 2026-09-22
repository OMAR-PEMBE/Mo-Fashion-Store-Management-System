<?php

namespace Tests\MySql;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerDatabaseTest extends TestCase
{
    public function test_mysql_customer_persistence_duplicate_check_and_unique_code(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        DB::beginTransaction();
        try {
            $this->seed(DatabaseSeeder::class);
            $actor = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $data = ['full_name' => 'Database customer', 'phone' => '255700987654'];
            $customer = app(CustomerService::class)->save($data, $actor);
            $this->assertSame('0.00', $customer->total_spent);
            try {
                app(CustomerService::class)->save($data, $actor);
                $this->fail('Expected duplicate warning.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('allow_duplicate', $exception->errors());
            }
            $other = app(CustomerService::class)->save($data + ['allow_duplicate' => 1], $actor);
            $this->assertNotSame($customer->customer_code, $other->customer_code);
            try {
                DB::table('customers')->where('id', $other->id)->update(['customer_code' => $customer->customer_code]);
                $this->fail('Expected unique constraint.');
            } catch (UniqueConstraintViolationException) {
                $this->assertSame(2, Customer::whereIn('id', [$customer->id, $other->id])->count());
            }
        } finally {
            DB::rollBack();
        }
    }
}
