<?php

namespace Tests\MySql;

use App\Enums\ReferenceType;
use App\Models\Role;
use App\Models\User;
use App\Services\ReferenceDataService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReferenceDataDatabaseTest extends TestCase
{
    public function test_reference_data_persistence_permissions_and_unique_constraints_on_mysql(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->withoutVite();
        DB::beginTransaction();
        try {
            $this->seed(DatabaseSeeder::class);
            $admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
            $this->actingAs($admin);
            $service = app(ReferenceDataService::class);
            foreach (ReferenceType::cases() as $type) {
                $data = ['name' => 'Integration example', $type->identifier() => 'integration-example',
                    'is_active' => true, 'sort_order' => 12, 'hex_code' => '#123456'];
                $record = $service->save($type, $data);
                $this->get('/reference-data/'.$type->value.'?q=Integration')->assertOk()->assertSee('Integration example');
                $this->assertTrue($record->fresh()->is_active);
                $service->save($type, array_replace($data, ['is_active' => false]), $record);
                $this->assertFalse($record->fresh()->is_active);
                try {
                    $service->save($type, $data);
                    $this->fail('Duplicate identifiers must fail validation.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey($type->identifier(), $exception->errors());
                }
                try {
                    $record->replicate()->save();
                    $this->fail('The database must independently reject duplicate identifiers.');
                } catch (UniqueConstraintViolationException) {
                    $this->assertTrue(true);
                }
            }
        } finally {
            DB::rollBack();
        }
    }
}
