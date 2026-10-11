<?php

namespace Tests\Feature;

use App\Enums\LotStatus;
use App\Enums\PropertyLocation;
use App\Enums\UserRole;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_only_listing_roles_can_browse_inventory_and_lot_details(string $role): void
    {
        $lot = Lot::factory()->create(['selling_price' => '654321.25', 'area_sqm' => '123.45', 'block_no' => 'B-2']);
        $property = $lot->property;
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('inventory.index'))->assertOk()->assertSee($property->name);
        $this->get(route('inventory.show', $property))->assertOk()->assertSee($lot->lot_no)->assertSee('Schematic lot layout')->assertSee('class="lot-parcel"', false)->assertSee('data-lot-dialog=', false)->assertSee('aria-labelledby="lot-heading-', false)->assertSee('do not represent a surveyed subdivision plan');
        $response = $this->get(route('inventory.lot', [$property, $lot]));
        $response->assertOk()->assertSee($property->name)->assertSee($property->location)->assertSee('B-2')->assertSee('123.45 m²')->assertSee('₱654,321.25')->assertSee('Available');
        $response->assertDontSee('Edit lot')->assertDontSee(route('properties.lots.edit', [$property, $lot]), false);
    }

    public function test_the_map_uses_database_lot_details_and_keeps_saved_position_across_filters(): void
    {
        $property = Property::factory()->create();
        $available = Lot::factory()->for($property)->create([
            'lot_no' => 'PERSISTENT-LOT', 'grid_row' => 3, 'grid_column' => 4,
            'area_sqm' => '135.50', 'selling_price' => '456789.25',
        ]);
        $reserved = Lot::factory()->for($property)->create([
            'lot_no' => 'RESERVED-LOT', 'grid_row' => 1, 'grid_column' => 2, 'status' => LotStatus::Reserved,
        ]);
        $this->actingAs(User::factory()->create());

        $availableView = $this->get(route('inventory.show', $property));
        $availableView->assertOk()
            ->assertSee('data-lot-id="'.$available->id.'"', false)
            ->assertSee('data-grid-row="3"', false)->assertSee('data-grid-column="4"', false)
            ->assertSee('₱456,789.25')->assertDontSee('RESERVED-LOT');

        $allView = $this->get(route('inventory.show', [$property, 'status' => 'all']));
        $allView->assertOk()->assertSee('PERSISTENT-LOT')->assertSee('RESERVED-LOT')
            ->assertSee('data-grid-row="3"', false)->assertSee('data-grid-column="4"', false)
            ->assertSee('data-grid-row="1"', false)->assertSee('data-grid-column="2"', false);

        $available->update(['selling_price' => '550000.00']);
        $this->get(route('inventory.show', $property))->assertOk()->assertSee('₱550,000.00')->assertDontSee('₱456,789.25');
        $this->assertSame([3, 4], [$available->fresh()->grid_row, $available->fresh()->grid_column]);
        $this->assertSame('reserved', $reserved->fresh()->status->value);
    }

    public static function roles(): array
    {
        return [['buyer'], ['agent'], ['admin'], ['unit_manager']];
    }

    public function test_location_filters_show_only_properties_with_available_lots_by_default(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (PropertyLocation::cases() as $location) {
            $available = Property::factory()->create(['name' => $location->value.' available estate', 'location' => $location->value]);
            $reserved = Property::factory()->create(['name' => $location->value.' reserved estate', 'location' => $location->value]);
            Property::factory()->create(['name' => $location->value.' empty estate', 'location' => $location->value]);
            Lot::factory()->for($available)->create();
            Lot::factory()->for($reserved)->create(['status' => 'reserved']);
        }
        foreach (PropertyLocation::cases() as $location) {
            $response = $this->get(route('inventory.index', ['location' => $location->value]));
            $response->assertOk()->assertViewHas('properties', fn ($properties) => $properties->total() === 1 && $properties->first()->location === $location->value);
            $response->assertSee($location->value.' available estate')->assertDontSee($location->value.' reserved estate')->assertDontSee($location->value.' empty estate');
            $this->get(route('inventory.index', ['location' => $location->value, 'availability' => 'all']))->assertOk()->assertViewHas('properties', fn ($properties) => $properties->total() === 3);
        }
        $this->get('/inventory')->assertOk()->assertViewHas('properties', fn ($properties) => $properties->total() === 3);
        $this->get('/inventory?availability=all')->assertOk()->assertViewHas('properties', fn ($properties) => $properties->total() === 9);
    }

    public function test_status_filters_and_counts_use_the_current_records(): void
    {
        $property = Property::factory()->create();
        foreach (LotStatus::cases() as $status) {
            Lot::factory()->for($property)->create(['status' => $status, 'lot_no' => 'Lot-'.$status->value]);
        }
        $this->actingAs(User::factory()->create());
        $this->get(route('inventory.show', $property))->assertOk()->assertViewHas('lots', fn ($lots) => $lots->total() === 1 && $lots->first()->status === LotStatus::Available);
        foreach (LotStatus::cases() as $status) {
            $this->get(route('inventory.show', [$property, 'status' => $status->value]))->assertOk()->assertViewHas('lots', fn ($lots) => $lots->total() === 1 && $lots->first()->status === $status);
        }
        $this->get(route('inventory.show', [$property, 'status' => 'all']))->assertOk()->assertViewHas('lots', fn ($lots) => $lots->total() === 4)->assertViewHas('counts', fn ($counts) => $counts->all() === ['available' => 1, 'fully_paid' => 1, 'reserved' => 1, 'sold' => 1]);
        $property->lots()->where('status', 'available')->update(['status' => 'reserved']);
        $this->get(route('inventory.show', $property))->assertOk()->assertSee('No lots with this status')->assertViewHas('counts', fn ($counts) => $counts['reserved'] === 2 && ! isset($counts['available']));
        $this->get('/inventory')->assertOk()->assertDontSee($property->name);
    }

    public function test_pagination_keeps_filters_and_provides_unique_dialog_ids(): void
    {
        $property = Property::factory()->create(['location' => 'Toril']);
        Lot::factory()->count(25)->for($property)->create(['status' => 'reserved']);
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('inventory.show', [$property, 'status' => 'reserved']));
        $response->assertOk()->assertViewHas('lots', fn ($lots) => $lots->count() === 24 && $lots->total() === 25)->assertSee('status=reserved', false);
        preg_match_all('/<dialog id="([^"]+)"/', $response->getContent(), $matches);
        $this->assertCount(24, $matches[1]);
        $this->assertCount(24, array_unique($matches[1]));
        $this->get(route('inventory.show', [$property, 'status' => 'reserved', 'page' => 2]))->assertOk()->assertViewHas('lots', fn ($lots) => $lots->count() === 1);

        Property::factory()->count(12)->create(['location' => 'Toril']);
        $this->get('/inventory?location=Toril&availability=all')->assertOk()->assertViewHas('properties', fn ($properties) => $properties->count() === 12 && $properties->total() === 13)->assertSee('location=Toril', false)->assertSee('availability=all', false);
    }

    public function test_inventory_filters_reject_unsupported_and_malformed_values(): void
    {
        $property = Property::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->getJson('/inventory?location=Davao')->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->getJson('/inventory?location[]=Toril')->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->getJson('/inventory?availability=invalid')->assertUnprocessable()->assertJsonValidationErrors('availability');
        $this->getJson(route('inventory.show', [$property, 'status' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/inventory/'.$property->id.'?status[]=available')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_record_text_is_escaped_in_cards_dialogs_details_and_management(): void
    {
        $unsafe = '<script>alert("inventory")</script>';
        $property = Property::factory()->create(['name' => $unsafe, 'description' => $unsafe, 'address' => $unsafe]);
        $lot = Lot::factory()->for($property)->create(['lot_no' => $unsafe, 'block_no' => $unsafe]);
        $this->actingAs(User::factory()->create(['role' => 'buyer']));
        foreach ([route('inventory.index'), route('inventory.show', $property), route('inventory.lot', [$property, $lot])] as $path) {
            $this->get($path)->assertOk()->assertSee($unsafe)->assertDontSee($unsafe, false);
        }
        $this->actingAs(User::factory()->create(['role' => 'field_planner']));
        foreach ([route('properties.show', $property), route('properties.edit', $property), route('properties.lots.edit', [$property, $lot])] as $path) {
            $this->get($path)->assertOk()->assertSee($unsafe)->assertDontSee($unsafe, false);
        }
    }

    public function test_empty_inventory_shows_helpful_empty_states_without_fake_properties(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/inventory')->assertOk()->assertSee('No matching properties')->assertViewHas('properties', fn ($properties) => $properties->total() === 0);
        $property = Property::factory()->create();
        $this->get(route('inventory.show', $property))->assertOk()->assertSee('No lots with this status');
        $this->assertDatabaseCount('lots', 0);
    }
}
