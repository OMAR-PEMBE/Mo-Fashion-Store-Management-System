<?php

namespace Tests\MySql;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoundationDatabaseTest extends TestCase
{
    public function test_mysql_connection_and_infrastructure_migrations(): void
    {
        // Fail before any write if configuration points at a non-test database.
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->artisan('app:check-database')->assertSuccessful();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('jobs'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('users'));
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('users', 'role_id'));

        DB::beginTransaction();
        try {
            $this->seed(RolePermissionSeeder::class);
            $user = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
            $this->assertTrue($user->hasPermission('sales.create'));
            $this->assertFalse($user->hasPermission('reports.view'));
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->post('/logout')->assertRedirect('/login');
        } finally {
            DB::rollBack();
        }
    }
}
