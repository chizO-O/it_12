<?php

namespace Tests\Feature;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PropertyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function planner(): User
    {
        return User::factory()->create(['role' => UserRole::FieldPlanner]);
    }

    private function lotInput(array $changes = []): array
    {
        return array_replace(['lot_no' => 'L-01', 'block_no' => null, 'area_sqm' => '120.25', 'selling_price' => '600000.75'], $changes);
    }

    #[DataProvider('nonPlannerRoles')]
    public function test_only_field_planners_can_access_or_mutate_management_pages(string $role): void
    {
        $property = Property::factory()->create();
        $lot = Lot::factory()->for($property)->create();
        $this->actingAs(User::factory()->create(['role' => $role]));

        foreach (['/properties', '/properties/create', "/properties/$property->id", "/properties/$property->id/edit", "/properties/$property->id/lots/create", "/properties/$property->id/lots/$lot->id/edit"] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/properties', ['name' => 'Forged', 'location' => 'Toril'])->assertForbidden();
        $this->put("/properties/$property->id", ['name' => 'Forged', 'location' => 'Toril'])->assertForbidden();
        $this->delete("/properties/$property->id")->assertForbidden();
        $this->post("/properties/$property->id/lots", $this->lotInput())->assertForbidden();
        $this->put("/properties/$property->id/lots/$lot->id", $this->lotInput())->assertForbidden();
        $this->delete("/properties/$property->id/lots/$lot->id")->assertForbidden();
        $this->assertSame($property->name, $property->fresh()->name);
        $this->assertSame($lot->lot_no, $lot->fresh()->lot_no);
        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('lots', 1);
    }

    public function test_field_planner_does_not_choose_rows_or_columns_for_automatic_placement(): void
    {
        $property = Property::factory()->create();
        $this->actingAs($this->planner());

        $this->get(route('properties.lots.create', $property))->assertOk()
            ->assertSee('Automatic lot placement')
            ->assertDontSee('name="grid_row"', false)
            ->assertDontSee('name="grid_column"', false);

        $this->post(route('properties.lots.store', $property), $this->lotInput([
            'lot_no' => '10', 'block_no' => '2',
        ]))->assertRedirect();
        $this->post(route('properties.lots.store', $property), $this->lotInput([
            'lot_no' => '2', 'block_no' => '2',
        ]))->assertRedirect();
        $this->assertSame(2, $property->lots()->count());
        $this->assertTrue($property->lots()->get()->every(fn ($lot) => $lot->grid_row === null && $lot->grid_column === null));

        foreach ([['grid_row' => 1], ['grid_column' => 3], ['grid_row' => 1, 'grid_column' => 2]] as $forged) {
            $this->postJson(route('properties.lots.store', $property), $this->lotInput(array_merge([
                'lot_no' => '3', 'block_no' => '2',
            ], $forged)))->assertUnprocessable()->assertJsonValidationErrors(array_keys($forged));
        }

        $this->get(route('properties.show', $property))->assertOk()
            ->assertSee('data-auto-position="block-lot"', false)
            ->assertSee('Block 2');
        $this->assertSame(2, $property->lots()->count());
    }

    public static function nonPlannerRoles(): array
    {
        return array_map(fn ($role) => [$role->value], array_filter(UserRole::cases(), fn ($role) => $role !== UserRole::FieldPlanner));
    }

    public function test_a_field_planner_can_create_update_and_delete_an_empty_property(): void
    {
        $this->actingAs($this->planner());
        $this->get('/properties/create')->assertOk();
        $this->post('/properties', ['name' => 'Authorized estate', 'location' => 'Toril', 'address' => 'Recorded address', 'total_area_sqm' => '1000.50', 'description' => 'Verified outside the application.', 'id' => 999])->assertRedirect();
        $property = Property::firstOrFail();
        $this->assertSame('1000.50', $property->total_area_sqm);
        $this->assertNotEquals(999, $property->id);
        $this->get(route('properties.show', $property))->assertOk()->assertSee('Authorized estate');
        $this->get(route('properties.edit', $property))->assertOk()->assertSee('1000.50');
        $this->put(route('properties.update', $property), ['name' => 'Updated estate', 'location' => 'Kapalong', 'total_area_sqm' => null])->assertRedirect(route('properties.show', $property));
        $this->assertSame('Kapalong', $property->fresh()->location);
        $this->assertNull($property->fresh()->total_area_sqm);
        $this->get('/properties?location=Kapalong')->assertOk()->assertSee('Updated estate');
        $this->get('/properties?location=Kidapawan')->assertOk()->assertDontSee('Updated estate');
        $this->delete(route('properties.destroy', $property))->assertRedirect(route('properties.index'));
        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    }

    public function test_property_validation_rejects_unsupported_locations_and_invalid_details(): void
    {
        $this->actingAs($this->planner());
        foreach (['Davao', ['Toril'], null] as $location) {
            $this->postJson('/properties', ['name' => 'Estate', 'location' => $location])->assertUnprocessable()->assertJsonValidationErrors('location');
        }
        $this->postJson('/properties', ['name' => ['invalid'], 'location' => 'Toril', 'address' => str_repeat('a', 256), 'total_area_sqm' => '-1', 'description' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors(['name', 'address', 'total_area_sqm', 'description']);
        foreach (['100.001', '10000000000', 0] as $area) {
            $this->postJson('/properties', ['name' => 'Estate', 'location' => 'Toril', 'total_area_sqm' => $area])->assertUnprocessable()->assertJsonValidationErrors('total_area_sqm');
        }
        $this->assertDatabaseCount('properties', 0);
    }

    public function test_a_field_planner_can_create_update_and_delete_an_available_lot(): void
    {
        $property = Property::factory()->create();
        $this->actingAs($this->planner());
        $this->get(route('properties.lots.create', $property))->assertOk()->assertSee($property->name);
        $this->post(route('properties.lots.store', $property), $this->lotInput())->assertRedirect(route('properties.show', $property));
        $lot = $property->lots()->firstOrFail();
        $this->assertSame(LotStatus::Available, $lot->status);
        $this->assertSame('120.25', $lot->area_sqm);
        $this->assertSame('600000.75', $lot->selling_price);
        $this->get(route('properties.lots.edit', [$property, $lot]))->assertOk()->assertSee('600000.75');
        $this->put(route('properties.lots.update', [$property, $lot]), $this->lotInput(['block_no' => 'A', 'selling_price' => '700000.50']))->assertRedirect(route('properties.show', $property));
        $this->assertSame('A', $lot->fresh()->block_no);
        $this->assertSame('700000.50', $lot->fresh()->selling_price);
        $this->assertSame(LotStatus::Available, $lot->fresh()->status);
        $this->delete(route('properties.lots.destroy', [$property, $lot]))->assertRedirect(route('properties.show', $property));
        $this->assertDatabaseMissing('lots', ['id' => $lot->id]);
    }

    public function test_lot_forms_cannot_change_status_or_parent_even_with_empty_forged_fields(): void
    {
        $property = Property::factory()->create();
        $other = Property::factory()->create();
        $lot = Lot::factory()->for($property)->create();
        $this->actingAs($this->planner());
        foreach (['reserved', 'sold', 'fully_paid', 'available'] as $status) {
            $this->postJson(route('properties.lots.store', $property), $this->lotInput(['status' => $status, 'property_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors(['status', 'property_id']);
            $this->putJson(route('properties.lots.update', [$property, $lot]), $this->lotInput(['status' => $status, 'property_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors(['status', 'property_id']);
        }
        $this->post(route('properties.lots.store', $property), $this->lotInput(['status' => null, 'property_id' => null]))->assertRedirect();
        $this->put(route('properties.lots.update', [$property, $lot]), $this->lotInput(['lot_no' => 'L-02', 'status' => '', 'property_id' => '']))->assertRedirect();
        $this->assertSame($property->id, $lot->fresh()->property_id);
        $this->assertSame(LotStatus::Available, $lot->fresh()->status);
        $this->assertSame(2, $property->lots()->where('status', 'available')->count());
        $this->assertSame(0, $other->lots()->count());
    }

    #[DataProvider('protectedStatuses')]
    public function test_reserved_sold_and_fully_paid_lots_cannot_be_edited_or_deleted(string $status): void
    {
        $lot = Lot::factory()->create(['status' => $status]);
        $original = $lot->fresh()->getRawOriginal();
        $property = $lot->property;
        $this->actingAs($this->planner());
        $this->get(route('properties.lots.edit', [$property, $lot]))->assertForbidden();
        $this->put(route('properties.lots.update', [$property, $lot]), $this->lotInput())->assertForbidden();
        $this->delete(route('properties.lots.destroy', [$property, $lot]))->assertForbidden();
        $this->get(route('properties.show', $property))->assertOk()->assertSee('Protected record')->assertDontSee(route('properties.lots.edit', [$property, $lot]), false);
        $this->assertSame($original, $lot->fresh()->getRawOriginal());
    }

    public static function protectedStatuses(): array
    {
        return [['reserved'], ['sold'], ['fully_paid']];
    }

    public function test_property_deletion_preserves_all_existing_lots(): void
    {
        $property = Property::factory()->create();
        foreach (LotStatus::cases() as $status) {
            Lot::factory()->for($property)->create(['status' => $status]);
        }
        $this->actingAs($this->planner())->deleteJson(route('properties.destroy', $property))->assertUnprocessable()->assertJsonValidationErrors('property');
        $this->assertDatabaseHas('properties', ['id' => $property->id]);
        $this->assertDatabaseCount('lots', 4);
    }

    public function test_lot_identity_is_unique_per_property_and_block_and_editing_self_is_allowed(): void
    {
        $property = Property::factory()->create();
        $lot = Lot::factory()->for($property)->create(['lot_no' => 'L-01', 'block_no' => 'A']);
        $this->actingAs($this->planner());
        $this->postJson(route('properties.lots.store', $property), $this->lotInput(['lot_no' => ' l-01 ', 'block_no' => ' a ']))->assertUnprocessable()->assertJsonValidationErrors('lot_no');
        $this->put(route('properties.lots.update', [$property, $lot]), $this->lotInput(['block_no' => 'A']))->assertRedirect();
        $this->post(route('properties.lots.store', $property), $this->lotInput(['block_no' => 'B']))->assertRedirect();
        $this->post(route('properties.lots.store', $property), $this->lotInput())->assertRedirect();
        $this->postJson(route('properties.lots.store', $property), $this->lotInput(['block_no' => '']))->assertUnprocessable()->assertJsonValidationErrors('lot_no');
        $other = Property::factory()->create();
        $this->post(route('properties.lots.store', $other), $this->lotInput(['block_no' => 'A']))->assertRedirect();
        $this->putJson(route('properties.lots.update', [$property, $lot]), $this->lotInput(['block_no' => 'B']))->assertUnprocessable()->assertJsonValidationErrors('lot_no');
        $this->assertSame('A', $lot->fresh()->block_no);
        $this->assertDatabaseCount('lots', 4);
    }

    public function test_database_enforces_lot_identity_even_when_form_validation_is_bypassed(): void
    {
        $property = Property::factory()->create();
        Lot::factory()->for($property)->create(['lot_no' => 'L-01', 'block_no' => null]);
        $this->expectException(UniqueConstraintViolationException::class);
        Lot::factory()->for($property)->create(['lot_no' => ' l-01 ', 'block_no' => '']);
    }

    public function test_identity_migration_stops_on_legacy_duplicates_without_changing_lots(): void
    {
        DB::statement('DROP INDEX lots_property_block_number_unique');
        $property = Property::factory()->create();
        Lot::factory()->for($property)->create(['lot_no' => 'L-01', 'block_no' => null]);
        Lot::factory()->for($property)->create(['lot_no' => ' l-01 ', 'block_no' => '']);
        $migration = require database_path('migrations/2026_10_10_110000_add_lot_identity_and_inventory_indexes.php');
        try {
            $migration->up();
            $this->fail('Duplicate lot numbers must block migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('no lots have been changed', $exception->getMessage());
        }
        $this->assertDatabaseCount('lots', 2);
        $this->assertDatabaseHas('lots', ['lot_no' => ' l-01 ', 'block_no' => '']);
    }

    public function test_nested_routes_cannot_read_edit_or_delete_a_lot_of_another_property(): void
    {
        $lot = Lot::factory()->create();
        $original = $lot->fresh()->getRawOriginal();
        $wrongProperty = Property::factory()->create();
        $this->actingAs($this->planner());
        $this->get(route('inventory.lot', [$wrongProperty, $lot]))->assertNotFound();
        $this->get(route('properties.lots.edit', [$wrongProperty, $lot]))->assertNotFound();
        $this->put(route('properties.lots.update', [$wrongProperty, $lot]), $this->lotInput())->assertNotFound();
        $this->delete(route('properties.lots.destroy', [$wrongProperty, $lot]))->assertNotFound();
        $this->assertSame($original, $lot->fresh()->getRawOriginal());
        $this->get('/inventory/999999/lots/999999')->assertNotFound();
        $this->post('/properties/999999/lots', $this->lotInput())->assertNotFound();
    }

    public function test_lot_validation_handles_invalid_numbers_and_malformed_input(): void
    {
        $property = Property::factory()->create();
        $this->actingAs($this->planner());
        foreach ([['area_sqm' => 0], ['selling_price' => -1], ['area_sqm' => '1.001'], ['selling_price' => '1.001'], ['area_sqm' => '10000000000'], ['selling_price' => '10000000000000'], ['area_sqm' => 'abc'], ['selling_price' => ['invalid']], ['lot_no' => ['invalid']], ['block_no' => ['invalid']], ['lot_no' => ' ']] as $invalid) {
            $this->postJson(route('properties.lots.store', $property), $this->lotInput($invalid))->assertUnprocessable()->assertJsonValidationErrors(array_key_first($invalid));
        }
        $this->assertDatabaseCount('lots', 0);
    }

    public function test_guests_and_unverified_planners_cannot_access_inventory_or_write_records(): void
    {
        $property = Property::factory()->create();
        $this->get('/inventory')->assertRedirect(route('login'));
        $this->get('/properties')->assertRedirect(route('login'));
        $this->post(route('properties.lots.store', $property), $this->lotInput())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create(['role' => 'field_planner']));
        $this->get('/inventory')->assertRedirect(route('verification.notice'));
        $this->get('/properties')->assertRedirect(route('verification.notice'));
        $this->post('/properties', ['name' => 'Forged', 'location' => 'Toril'])->assertRedirect(route('verification.notice'));
        $this->post(route('properties.lots.store', $property), $this->lotInput())->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('lots', 0);
    }

    #[DataProvider('mutations')]
    public function test_a_status_change_after_initial_authorization_blocks_the_mutation(string $operation): void
    {
        $lot = Lot::factory()->create();
        $property = $lot->property;
        $changed = false;
        Gate::after(function ($user, $ability, $result, $arguments) use ($lot, &$changed) {
            if (! $changed && $result === true && in_array($ability, ['update', 'delete'], true) && ($arguments[0] ?? null) instanceof Lot) {
                $changed = true;
                Lot::whereKey($lot->id)->update(['status' => 'reserved']);
            }
        });
        $this->actingAs($this->planner());
        if ($operation === 'update') {
            $this->put(route('properties.lots.update', [$property, $lot]), $this->lotInput())->assertForbidden();
        } else {
            $this->delete(route('properties.lots.destroy', [$property, $lot]))->assertForbidden();
        }
        $this->assertTrue($changed);
        $this->assertSame(LotStatus::Reserved, $lot->fresh()->status);
        $this->assertSame($lot->lot_no, $lot->fresh()->lot_no);
        $this->assertSame($lot->selling_price, $lot->fresh()->selling_price);
    }

    public static function mutations(): array
    {
        return [['update'], ['delete']];
    }

    public function test_validation_redirects_render_safely_after_malformed_form_input(): void
    {
        $property = Property::factory()->create();
        $this->actingAs($this->planner());
        $this->from(route('properties.create'))->post(route('properties.store'), ['name' => ['bad'], 'location' => ['bad'], 'description' => ['bad']])->assertSessionHasErrors(['name', 'location', 'description']);
        $this->get(route('properties.create'))->assertOk()->assertSee('Please check the following:');
        $this->from(route('properties.lots.create', $property))->post(route('properties.lots.store', $property), $this->lotInput(['lot_no' => ['bad'], 'block_no' => ['bad'], 'selling_price' => ['bad']]))->assertSessionHasErrors(['lot_no', 'block_no', 'selling_price']);
        $this->get(route('properties.lots.create', $property))->assertOk()->assertSee('Please check the following:');
        $this->assertDatabaseCount('lots', 0);
    }
}
