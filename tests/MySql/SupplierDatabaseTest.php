<?php

namespace Tests\MySql;

use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierDatabaseTest extends TestCase
{
    public function test_mysql_supplier_persistence_and_unique_code(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->withoutVite();
        DB::beginTransaction();
        try {
            $this->seed(DatabaseSeeder::class);
            $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]));
            $data = ['name' => 'Integration supplier', 'supplier_code' => 'INTEGRATION-SUP', 'is_active' => 1, 'phone' => '+255 700 000 000'];
            $supplier = app(SupplierService::class)->save($data);
            $this->get('/suppliers/'.$supplier->id)->assertOk()->assertSee('Integration supplier');
            app(SupplierService::class)->save(array_replace($data, ['is_active' => 0]), $supplier);
            $this->assertFalse($supplier->fresh()->is_active);
            $this->assertSame($data['phone'], $supplier->fresh()->phone);
            try {
                Supplier::create($data);
                $this->fail('The database must reject duplicate supplier codes.');
            } catch (UniqueConstraintViolationException) {
                $this->assertTrue(true);
            }
            $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
        } finally {
            DB::rollBack();
        }
    }
}
