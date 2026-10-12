<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class EmployeeActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_self_registers_with_password_and_no_email_verification(): void
    {
        Notification::fake();
        $response = $this->post('/register', [
            'name' => 'Dummy Buyer', 'email' => 'dummy-buyer@example.com',
            'password' => 'twelve-plus-chars', 'password_confirmation' => 'twelve-plus-chars',
        ]);
        $response->assertRedirect('/dashboard');
        $user = User::where('email', 'dummy-buyer@example.com')->sole();
        $this->assertTrue($user->hasVerifiedEmail());
        Notification::assertNotSentTo($user, VerifyEmail::class);
        $this->get(route('buyer.dashboard'))->assertOk();
    }

    public function test_secretary_creates_pending_employee_and_gets_private_setup_code(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $result = $this->actingAs($admin)->post(route('employees.store'), [
            'name' => 'Test Cashier', 'email' => 'TEST-CASHIER@example.com', 'role' => 'cashier',
            'password' => 'forged', 'email_verified_at' => now(),
        ]);
        $result->assertRedirect(route('employees.index'))->assertSessionHas('activation_code');
        $employee = User::where('email', 'test-cashier@example.com')->sole();
        $this->assertNull($employee->password);
        $this->assertNull($employee->email_verified_at);
        $this->assertSame($admin->id, $employee->invited_by_user_id);
        $code = session('activation_code');
        $this->assertGreaterThanOrEqual(32, strlen($code));
        $this->assertTrue(Password::broker()->tokenExists($employee, $code));
        Notification::assertNotSentTo($employee, ResetPassword::class);
    }

    public function test_wrong_code_and_wrong_employee_email_cannot_activate_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create([
            'role' => 'cashier', 'password' => null, 'invited_by_user_id' => $admin->id,
            'email_verified_at' => null,
        ]);
        $other = User::factory()->create([
            'role' => 'field_planner', 'password' => null, 'invited_by_user_id' => $admin->id,
            'email_verified_at' => null,
        ]);
        $code = Password::broker()->createToken($employee);
        $payload = ['code' => $code, 'password' => 'secure-demo-password',
            'password_confirmation' => 'secure-demo-password'];
        $this->post(route('employee-activation.store'), $payload + ['email' => $other->email])
            ->assertSessionHasErrors('code');
        $this->post(route('employee-activation.store'), ['email' => $employee->email,
            'code' => 'wrong-code-with-32-characters-1234567890'] + $payload)
            ->assertSessionHasErrors('code');
        $this->assertNull($employee->fresh()->password);
        $this->assertNull($other->fresh()->password);
    }

    public function test_one_time_code_can_only_be_used_once_and_password_is_hashed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create([
            'role' => 'agent', 'password' => null, 'invited_by_user_id' => $admin->id,
            'email_verified_at' => null,
        ]);
        $code = Password::broker()->createToken($employee);
        $payload = ['email' => strtoupper($employee->email), 'code' => $code,
            'password' => 'secure-demo-password', 'password_confirmation' => 'secure-demo-password'];
        $this->post(route('employee-activation.store'), $payload)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('secure-demo-password', $employee->fresh()->password));
        $this->assertTrue($employee->fresh()->hasVerifiedEmail());
        $this->assertFalse(Password::broker()->tokenExists($employee, $code));
        $this->post(route('employee-activation.store'), $payload)->assertSessionHasErrors('code');
    }

    public function test_code_expires_and_manager_cannot_issue_code_for_others_agents(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'unit_manager']);
        $otherManager = User::factory()->create(['role' => 'unit_manager']);
        $agent = User::factory()->create([
            'role' => 'agent', 'password' => null, 'unit_manager_id' => $manager->id,
            'invited_by_user_id' => $manager->id, 'email_verified_at' => null,
        ]);
        $this->actingAs($otherManager)->post(route('agents.invite', $agent))->assertForbidden();
        $this->post('/logout');
        $code = Password::broker()->createToken($agent);
        $this->travel(61)->minutes();
        $this->post(route('employee-activation.store'), [
            'email' => $agent->email, 'code' => $code,
            'password' => 'secure-demo-password', 'password_confirmation' => 'secure-demo-password',
        ])->assertSessionHasErrors('code');
        $this->assertNull($agent->fresh()->password);
    }

    public function test_reissuing_a_code_invalidates_the_previous_code(): void
    {
        $manager = User::factory()->create(['role' => 'unit_manager']);
        $agent = User::factory()->create([
            'role' => 'agent', 'password' => null, 'unit_manager_id' => $manager->id,
            'invited_by_user_id' => $manager->id, 'email_verified_at' => null,
        ]);
        $old = Password::broker()->createToken($agent);
        $response = $this->actingAs($manager)->post(route('agents.invite', $agent));
        $response->assertSessionHas('activation_code');
        $new = session('activation_code');
        $this->assertFalse(Password::broker()->tokenExists($agent, $old));
        $this->assertTrue(Password::broker()->tokenExists($agent, $new));
        $page = $this->get(route('agents.index'))->assertOk();
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
    }

    public function test_buyer_cannot_claim_employee_activation_even_with_reset_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'buyer', 'password' => null, 'invited_by_user_id' => $admin->id]);
        $code = Password::broker()->createToken($buyer);
        $this->post(route('employee-activation.store'), [
            'email' => $buyer->email, 'code' => $code,
            'password' => 'secure-demo-password', 'password_confirmation' => 'secure-demo-password',
        ])->assertSessionHasErrors('code');
    }
}
