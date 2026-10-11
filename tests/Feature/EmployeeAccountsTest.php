<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_an_employee_with_an_authorized_role(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/employees')->assertOk()->assertSee('Employee accounts');
        $this->post('/employees', [
            'name' => 'Cashier One', 'email' => 'CASHIER@example.com', 'role' => 'cashier',
            'password' => 'forged-password', 'email_verified_at' => now(),
        ])->assertRedirect(route('employees.index'))->assertSessionHas('status');

        $employee = User::where('email', 'cashier@example.com')->firstOrFail();
        $this->assertSame(UserRole::Cashier, $employee->role);
        $this->assertNull($employee->password);
        $this->assertNull($employee->email_verified_at);
        Notification::assertSentTo($employee, ResetPassword::class);
        $this->get('/employees')->assertSee('Setup pending')->assertSee('cashier@example.com');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_cannot_list_create_or_invite_employees(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $employee = User::factory()->create(['role' => 'field_planner', 'password' => null]);

        $this->actingAs($user)->get('/employees')->assertForbidden();
        $this->post('/employees', ['name' => 'Forged', 'email' => 'forged@example.com', 'role' => 'admin'])->assertForbidden();
        $this->post(route('employees.invite', $employee))->assertForbidden();
        $this->patch(route('employees.role', $employee), ['role' => 'admin'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'forged@example.com']);
        $this->get(route($user->role->dashboardRoute()))->assertDontSee('Manage employees');
    }

    public static function nonAdminRoles(): array
    {
        return [['buyer'], ['agent'], ['unit_manager'], ['field_planner'], ['cashier']];
    }

    public function test_invalid_roles_and_duplicate_employee_emails_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        foreach (['buyer', 'agent', 'super_admin', 'secretary'] as $role) {
            $this->post('/employees', ['name' => 'Invalid', 'email' => 'invalid@example.com', 'role' => $role])->assertSessionHasErrors('role');
        }

        $this->post('/employees', ['name' => 'Duplicate', 'email' => $admin->email, 'role' => 'cashier'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invited_employee_sets_password_then_verifies_email_before_office_access(): void
    {
        Notification::fake();
        $employee = User::factory()->unverified()->create(['role' => 'field_planner', 'password' => null]);
        $token = Password::createToken($employee);

        $this->post('/reset-password', [
            'email' => $employee->email, 'token' => $token,
            'password' => 'a-field-planner-password', 'password_confirmation' => 'a-field-planner-password',
            'role' => 'admin',
        ])->assertRedirect('/login');
        $this->assertTrue(Hash::check('a-field-planner-password', $employee->fresh()->password));
        $this->assertSame(UserRole::FieldPlanner, $employee->fresh()->role);
        Notification::assertSentTo($employee, VerifyEmail::class);

        $this->post('/login', ['email' => $employee->email, 'password' => 'a-field-planner-password'])->assertRedirect('/dashboard');
        $this->get('/office/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_admin_can_resend_pending_invitation_but_cannot_invite_buyers_or_active_employees(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->unverified()->create(['role' => 'field_planner', 'password' => null]);
        $buyer = User::factory()->create();
        $active = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($admin)->post(route('employees.invite', $employee))->assertSessionHas('status');
        Notification::assertSentTo($employee, ResetPassword::class);
        $this->post(route('employees.invite', $buyer))->assertForbidden();
        $this->post(route('employees.invite', $active))->assertForbidden();
        $legacyAgent = User::factory()->unverified()->create(['role' => 'agent', 'password' => null]);
        $this->post(route('employees.invite', $legacyAgent))->assertForbidden();
    }

    public function test_first_admin_command_has_no_default_password_and_cannot_be_used_twice(): void
    {
        Notification::fake();

        $this->artisan('grove:create-admin', ['--name' => 'First Admin', '--email' => 'ADMIN@example.com'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-private-admin-password')
            ->expectsQuestion('Confirm password', 'a-private-admin-password')
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('a-private-admin-password', $admin->password));
        $this->assertFalse($admin->hasVerifiedEmail());
        Notification::assertSentTo($admin, VerifyEmail::class);
        $this->artisan('grove:create-admin')->assertExitCode(1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_update_an_employee_role_without_changing_other_account_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'field_planner']);
        $password = $employee->password;
        $email = $employee->email;

        $this->actingAs($admin)->patch(route('employees.role', $employee), [
            'role' => 'cashier', 'email' => 'forged@example.com', 'password' => 'forged-password',
        ])->assertRedirect(route('employees.index'))->assertSessionHas('status');

        $employee->refresh();
        $this->assertSame(UserRole::Cashier, $employee->role);
        $this->assertSame($password, $employee->password);
        $this->assertSame($email, $employee->email);

        $this->actingAs($employee)->get('/agent/dashboard')->assertForbidden();
        $this->get('/dashboard')->assertRedirect(route('office.dashboard'));
        $this->get('/employees')->assertForbidden();
    }

    public function test_admin_cannot_change_their_own_role_promote_a_buyer_or_assign_invalid_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create();
        $employee = User::factory()->create(['role' => 'field_planner']);

        $this->actingAs($admin)->patch(route('employees.role', $admin), ['role' => 'cashier'])->assertForbidden();
        $this->patch(route('employees.role', $buyer), ['role' => 'admin'])->assertForbidden();

        foreach (['buyer', 'agent', 'owner', 'secretary'] as $role) {
            $this->patch(route('employees.role', $employee), ['role' => $role])->assertSessionHasErrors('role');
        }

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
        $this->assertSame(UserRole::Buyer, $buyer->fresh()->role);
        $this->assertSame(UserRole::FieldPlanner, $employee->fresh()->role);
    }
}
