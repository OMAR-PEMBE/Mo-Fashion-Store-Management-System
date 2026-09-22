<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType as Type;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductCatalogueService;
use App\Support\InventoryContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductVariant $variant;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->value('id')]);
        $category = Category::create(['name' => 'Inventory tests', 'slug' => 'inventory-tests']);
        $catalogue = app(ProductCatalogueService::class);
        $product = $catalogue->saveProduct(['name' => 'Test product', 'product_code' => 'TEST', 'category_id' => $category->id, 'is_active' => 1], $this->admin);
        $this->variant = $catalogue->saveVariant($product, ['sku' => 'TEST-SKU', 'selling_price' => '100', 'low_stock_threshold' => 2, 'is_active' => 1]);
        $this->service = app(InventoryService::class);
    }

    private function context(string $key, int $reference = 1, ?string $reason = null): InventoryContext
    {
        return new InventoryContext($this->admin, $key, 'test_document', $reference, $reason);
    }

    private function balance(int $physical, int $reserved): void
    {
        $row = $this->variant->inventory()->firstOrFail();
        $this->assertSame($physical, $row->physical_quantity);
        $this->assertSame($reserved, $row->reserved_quantity);
        $this->assertSame($physical - $reserved, $row->available_quantity);
    }

    public function test_zero_initialization_is_idempotent_and_stock_free(): void
    {
        $this->service->initialize($this->variant);
        $this->balance(0, 0);
        $this->assertDatabaseCount('inventories', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_stock_and_reservation_lifecycle_has_complete_ledger(): void
    {
        $this->service->increase($this->variant, 10, Type::Purchase, $this->context('purchase'));
        $reservation = $this->service->reserve($this->variant, 4, $this->context('reserve', 2));
        $this->assertSame(0, $reservation->quantity_change);
        $this->balance(10, 4);
        $this->service->decrease($this->variant, 2, Type::Sale, $this->context('sale', 3));
        $this->service->releaseReservation($this->variant, 1, $this->context('release', 2));
        $completion = $this->service->completeReservation($this->variant, 3, $this->context('complete', 2));
        $this->balance(5, 0);
        $this->assertSame(-3, $completion->quantity_change);
        $this->assertSame(8, $completion->physical_quantity_before);
        $this->assertSame(3, $completion->reserved_quantity_before);
        $this->assertSame($this->admin->id, $completion->created_by);
        $this->assertDatabaseCount('inventory_movements', 5);
        $this->assertSame('0.00', $this->variant->fresh()->weighted_average_cost);
    }

    public function test_insufficient_available_stock_cannot_consume_reservations(): void
    {
        $this->service->increase($this->variant, 5, Type::OpeningBalance, $this->context('opening'));
        $this->service->reserve($this->variant, 4, $this->context('reserve'));
        foreach (['decrease', 'reserve', 'adjust'] as $method) {
            try {
                match ($method) {
                    'decrease' => $this->service->decrease($this->variant, 2, Type::Sale, $this->context('too-many')),
                    'reserve' => $this->service->reserve($this->variant, 2, $this->context('too-many')),
                    'adjust' => $this->service->adjust($this->variant, -2, $this->context('too-many', reason: 'Stock count')),
                };
                $this->fail('Insufficient available stock must fail.');
            } catch (ValidationException) {
                $this->balance(5, 4);
                $this->assertDatabaseCount('inventory_movements', 2);
            }
        }
    }

    public function test_another_reference_cannot_release_or_complete_reserved_stock(): void
    {
        $this->service->increase($this->variant, 10, Type::Purchase, $this->context('purchase'));
        $this->service->reserve($this->variant, 3, $this->context('reserve', 5));
        foreach (['releaseReservation', 'completeReservation'] as $method) {
            try {
                $this->service->$method($this->variant, 1, $this->context($method, 6));
                $this->fail('Reference ownership must be enforced.');
            } catch (ValidationException) {
                $this->balance(10, 3);
            }
        }
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_retry_is_idempotent_and_mismatched_reuse_fails(): void
    {
        $first = $this->service->increase($this->variant, 5, Type::Purchase, $this->context('stable-key'));
        $retry = $this->service->increase($this->variant, 5, Type::Purchase, $this->context('stable-key'));
        $this->assertSame($first->id, $retry->id);
        try {
            $this->service->increase($this->variant, 6, Type::Purchase, $this->context('stable-key'));
            $this->fail('Reusing a key for another payload must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation_key', $exception->errors());
        }
        $this->balance(5, 0);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public static function invalidQuantities(): array
    {
        return [[0], [-1], [1.5], ['3'], [2147483648]];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_leave_no_partial_change(mixed $quantity): void
    {
        try {
            $this->service->increase($this->variant, $quantity, Type::Purchase, $this->context('bad'));
            $this->fail('Invalid quantity accepted.');
        } catch (ValidationException) {
            $this->balance(0, 0);
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    public function test_adjustment_requires_reason_and_valid_movement_direction(): void
    {
        foreach ([fn () => $this->service->adjust($this->variant, 3, $this->context('adjust')),
            fn () => $this->service->increase($this->variant, 3, Type::Sale, $this->context('wrong-type'))] as $action) {
            try {
                $action();
                $this->fail('Invalid operation accepted.');
            } catch (ValidationException) {
                $this->balance(0, 0);
            }
        }
        $this->service->adjust($this->variant, 5, $this->context('count', reason: 'Verified count'));
        $this->service->adjust($this->variant, -2, $this->context('correction', reason: 'Correct counted quantity'));
        $this->balance(3, 0);
    }

    public function test_ledger_failure_rolls_back_balance(): void
    {
        DB::unprepared("CREATE TRIGGER reject_movement BEFORE INSERT ON inventory_movements BEGIN SELECT RAISE(ABORT, 'Simulated ledger failure'); END");
        try {
            $this->service->increase($this->variant, 5, Type::Purchase, $this->context('failure'));
            $this->fail('Ledger failure must abort the operation.');
        } catch (QueryException) {
            $this->balance(0, 0);
            $this->assertDatabaseCount('inventory_movements', 0);
        } finally {
            DB::unprepared('DROP TRIGGER reject_movement');
        }
    }

    public function test_outer_workflow_rollback_also_rolls_back_inventory(): void
    {
        DB::beginTransaction();
        $this->service->increase($this->variant, 7, Type::Purchase, $this->context('outer'));
        DB::rollBack();
        $this->balance(0, 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_regular_model_writes_and_ledger_edits_are_blocked(): void
    {
        $movement = $this->service->increase($this->variant, 1, Type::Purchase, $this->context('one'));
        foreach ([$movement, $this->variant->inventory()->firstOrFail()] as $record) {
            try {
                $record->save();
                $this->fail('Direct writes must be rejected.');
            } catch (\LogicException) {
                $this->assertTrue(true);
            }
            try {
                $record->delete();
                $this->fail('Deletion must be rejected.');
            } catch (\LogicException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_read_permissions_and_service_authorization(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
        $salesperson = User::factory()->create(['role_id' => Role::where('slug', 'salesperson')->value('id')]);
        $this->actingAs($salesperson)->get('/inventory')->assertOk()->assertSee('TEST-SKU')->assertDontSee('Movements');
        $this->get('/inventory/'.$this->variant->id.'/movements')->assertForbidden();
        $this->post('/inventory')->assertMethodNotAllowed();
        try {
            $this->service->adjust($this->variant, 1, new InventoryContext($salesperson, 'unauthorized', 'test_document', 1, 'Count'));
            $this->fail('Salesperson adjusted stock.');
        } catch (AuthorizationException) {
            $this->balance(0, 0);
        }
        $this->actingAs($this->admin)->get('/inventory/'.$this->variant->id.'/movements')->assertOk()->assertSee('No stock movements');
        $this->service->increase($this->variant, 10, Type::Purchase, $this->context('in'));
        $this->get('/inventory?low_stock=1')->assertDontSee('TEST-SKU');
        $this->get('/inventory/'.$this->variant->id.'/movements')->assertSee('PURCHASE');
    }

    public function test_archived_stock_remains_visible_to_admin_and_can_release_existing_reservations(): void
    {
        $this->service->increase($this->variant, 5, Type::Purchase, $this->context('in'));
        $this->service->reserve($this->variant, 2, $this->context('reserve'));
        $this->variant->delete();
        $this->service->releaseReservation($this->variant, 2, $this->context('release'));
        $this->balance(5, 0);
        $this->actingAs($this->admin)->get('/inventory')->assertSee('TEST-SKU')->assertSee('Archived');
        $this->expectException(ValidationException::class);
        $this->service->reserve($this->variant, 1, $this->context('new-reservation'));
    }

    public function test_inventory_history_prevents_changing_variant_dimensions(): void
    {
        $this->service->increase($this->variant, 1, Type::Purchase, $this->context('stock'));
        $size = Size::where('code', 'M')->firstOrFail();
        $this->actingAs($this->admin)->put('/products/'.$this->variant->product_id.'/variants/'.$this->variant->id,
            ['sku' => 'TEST-SKU', 'size_id' => $size->id, 'selling_price' => '100', 'low_stock_threshold' => 2, 'is_active' => 1])->assertSessionHasErrors('size_id');
        $this->assertNull($this->variant->fresh()->size_id);
        $this->balance(1, 0);
    }

    public function test_quantity_overflow_is_rejected_and_disabled_actors_cannot_write(): void
    {
        $this->service->increase($this->variant, InventoryService::MAX_QUANTITY, Type::Purchase, $this->context('maximum'));
        try {
            $this->service->increase($this->variant, 1, Type::Purchase, $this->context('overflow'));
            $this->fail('Quantity overflow accepted.');
        } catch (ValidationException) {
            $this->balance(InventoryService::MAX_QUANTITY, 0);
        }
        DB::table('users')->where('id', $this->admin->id)->update(['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        $this->service->decrease($this->variant, 1, Type::Sale, $this->context('disabled'));
    }
}
