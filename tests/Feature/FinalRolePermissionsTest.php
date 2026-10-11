<?php

namespace Tests\Feature;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinalRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('allRoles')]
    public function test_each_role_can_open_only_its_core_navigation_pages(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        $allowed = [
            'buyer' => ['inventory.index', 'inquiries.index', 'reservations.index', 'cts.index', 'sales.index'],
            'agent' => ['inventory.index', 'inquiries.index', 'reservations.index'],
            'admin' => ['inventory.index', 'employees.index', 'cts.index', 'reports.index'],
            'unit_manager' => ['inventory.index', 'agents.index', 'assignments.index', 'reservations.index', 'sales.index', 'reports.index'],
            'field_planner' => ['properties.index', 'cts.index'],
            'cashier' => ['sales.index'],
        ][$role];

        foreach (['inventory.index', 'inquiries.index', 'reservations.index', 'cts.index',
            'sales.index', 'reports.index', 'employees.index', 'agents.index',
            'assignments.index', 'properties.index'] as $name) {
            $response = $this->get(route($name));
            $this->assertSame(in_array($name, $allowed, true) ? 200 : 403,
                $response->status(), "Unexpected status for $role on $name");
        }
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('account'))->assertOk();
    }

    #[DataProvider('allRoles')]
    public function test_inventory_details_are_not_a_backdoor_for_unlisted_roles(string $role): void
    {
        $lot = Lot::factory()->create(['status' => LotStatus::Available]);
        $this->actingAs(User::factory()->create(['role' => $role]));
        $permitted = in_array($role, ['buyer', 'agent', 'admin', 'unit_manager'], true);
        foreach ([route('inventory.show', $lot->property), route('inventory.lot', [$lot->property, $lot])] as $path) {
            $this->assertSame($permitted ? 200 : 403, $this->get($path)->status());
        }
    }

    public function test_unit_manager_only_sees_unconfirmed_sales_for_own_team(): void
    {
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $this->actingAs($manager)->get(route('sales.index'))->assertOk()
            ->assertSee('Sale confirmations')
            ->assertDontSee('Record & Verify Payment');
    }

    public function test_unassigned_inquiry_notification_takes_manager_to_lot_assignments(): void
    {
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $lot = Lot::factory()->create(['status' => LotStatus::Available]);

        $this->actingAs($buyer)->post(route('inquiries.store', [$lot->property, $lot]), [
            'message' => 'Please assign an Agent to this lot.',
        ])->assertRedirect();

        $notification = $manager->notifications()->firstOrFail();
        $this->actingAs($manager)->post(route('notifications.open', $notification->id))
            ->assertRedirect(route('assignments.index'));
        $this->get(route('assignments.index'))->assertOk()->assertSee('1 inquiry awaiting assignment');
        $this->get(route('inquiries.index'))->assertForbidden();
    }

    public function test_admin_can_view_specific_reservation_for_cts_but_not_reservations_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('reservations.index'))->assertForbidden();
        $this->post(route('reservations.notifications.read'))->assertForbidden();
    }

    public static function allRoles(): array
    {
        return array_map(fn (UserRole $role) => [$role->value], UserRole::cases());
    }
}
