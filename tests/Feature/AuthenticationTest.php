<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_authentication_pages_render(): void
    {
        foreach (['/', '/login', '/register', '/forgot-password', '/reset-password/example-token?email=buyer@example.com'] as $path) {
            $this->get($path)->assertOk()->assertSee('GroveMap');
        }
    }

    public function test_registration_always_creates_an_unverified_buyer_and_sends_verification(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Buyer One',
            'email' => 'BUYER@example.com',
            'password' => 'a-long-buyer-password',
            'password_confirmation' => 'a-long-buyer-password',
            'role' => 'admin',
            'email_verified_at' => now(),
            'google_id' => 'forged-google-id',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'buyer@example.com')->firstOrFail();
        $this->assertSame(UserRole::Buyer, $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->google_id);
        $this->assertTrue(Hash::check('a-long-buyer-password', $user->password));
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->get('/email/verify')->assertOk()->assertSee('buyer@example.com');
    }

    public function test_registration_rejects_duplicate_emails_and_short_passwords(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Buyer', 'email' => 'taken@example.com',
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('roles')]
    public function test_each_role_can_log_in_and_reach_only_its_dashboard(string $role, string $dashboard): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertRedirect(route($dashboard));
        $this->get(route($dashboard))->assertOk()->assertSee($user->role->label());
        $this->get('/account')->assertOk()->assertSee($user->email);

        foreach (['buyer.dashboard', 'agent.dashboard', 'office.dashboard'] as $other) {
            if ($other !== $dashboard) {
                $this->get(route($other))->assertForbidden();
            }
        }
    }

    public static function roles(): array
    {
        return [
            ['buyer', 'buyer.dashboard'], ['agent', 'agent.dashboard'],
            ['unit_manager', 'office.dashboard'], ['field_planner', 'office.dashboard'],
            ['admin', 'office.dashboard'], ['cashier', 'office.dashboard'],
        ];
    }

    public function test_invalid_credentials_and_google_only_password_login_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $googleOnly = User::factory()->create(['password' => null, 'google_id' => 'google-only']);
        $this->post('/login', ['email' => $googleOnly->email, 'password' => 'anything'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_registration_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/register', [])->assertSessionHasErrors();
        }

        $this->post('/register', [])->assertStatus(429);
    }

    public function test_guests_and_unverified_users_cannot_access_private_pages(): void
    {
        $paths = ['/dashboard', '/buyer/dashboard', '/agent/dashboard', '/office/dashboard', '/employees', '/account'];

        foreach ($paths as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->unverified()->create(['role' => 'admin']));

        foreach ($paths as $path) {
            $this->get($path)->assertRedirect(route('verification.notice'));
        }

        $this->post('/employees', [])->assertRedirect(route('verification.notice'));
    }

    public function test_signed_verification_link_verifies_the_correct_user(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect('/dashboard?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_invalid_expired_and_other_users_verification_links_are_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->get('/email/verify/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinute(), [
            'id' => $other->id, 'hash' => sha1($other->email),
        ]))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification')
            ->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_password_reset_uses_a_valid_single_use_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $payload = [
            'email' => $user->email, 'token' => $token,
            'password' => 'a-new-long-password', 'password_confirmation' => 'a-new-long-password',
        ];
        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->assertTrue(Hash::check('a-new-long-password', $user->fresh()->password));
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_invalid_reset_token_cannot_change_a_password(): void
    {
        $user = User::factory()->create();
        $before = $user->password;

        $this->post('/reset-password', [
            'email' => $user->email, 'token' => 'invalid-token',
            'password' => 'a-new-long-password', 'password_confirmation' => 'a-new-long-password',
        ])->assertSessionHasErrors('email');

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_logout_invalidates_access_to_private_pages(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/buyer/dashboard')->assertRedirect(route('login'));
    }

    public function test_a_password_change_invalidates_an_existing_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/buyer/dashboard')->assertOk();

        $user->forceFill(['password' => 'a-replacement-password'])->save();

        $this->get('/buyer/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
