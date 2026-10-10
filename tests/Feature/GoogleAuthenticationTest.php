<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_google_button_is_shown_only_when_configured_and_missing_configuration_fails_gracefully(): void
    {
        $this->get('/login')->assertSee('Continue with Google');
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
        $this->get('/login')->assertDontSee('Continue with Google');
        $this->get('/auth/google')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_redirect_creates_an_oauth_state_in_the_session(): void
    {
        // Exercise the actual provider's local redirect logic without making a Google request.
        $response = $this->get('/auth/google');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $response->headers->get('Location'));
        $response->assertSessionHas('state');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(session('state'), $query['state']);
        $this->assertSame('test-client', $query['client_id']);
    }

    public function test_actual_socialite_provider_rejects_missing_oauth_state_without_network_access(): void
    {
        $this->get('/auth/google/callback?code=untrusted-code')
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_new_verified_google_account_becomes_a_buyer_without_a_local_password(): void
    {
        $this->mockGoogleUser('new-google-id', 'BUYER@example.com');

        $this->get('/auth/google/callback?role=admin')->assertRedirect(route('dashboard'));

        $user = User::where('email', 'buyer@example.com')->firstOrFail();
        $this->assertSame(UserRole::Buyer, $user->role);
        $this->assertSame('new-google-id', $user->google_id);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $this->get('/buyer/dashboard')->assertOk();
        $this->get('/office/dashboard')->assertForbidden();
    }

    public function test_returning_google_user_keeps_their_existing_role(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'google_id' => 'cashier-google-id']);
        $this->mockGoogleUser('cashier-google-id', $user->email);

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Cashier, $user->fresh()->role);
        $this->get('/dashboard')->assertRedirect(route('office.dashboard'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_matching_email_does_not_allow_google_to_take_over_an_existing_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mockGoogleUser('unlinked-google-id', $admin->email);

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($admin->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unverified_provider_email_cannot_create_or_sign_into_an_account(): void
    {
        $this->mockGoogleUser('unverified-google-id', 'unverified@example.com', false);
        $this->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_cancelled_oauth_and_provider_failures_leave_the_browser_signed_out(): void
    {
        $this->withSession(['state' => 'old-state'])->get('/auth/google/callback?error=access_denied')
            ->assertSessionHasErrors('google')->assertSessionMissing('state');
        $this->assertGuest();

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_linking_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account/google')->assertRedirect(route('password.confirm'));
        $this->assertNull($user->fresh()->google_id);
        $this->get('/user/confirm-password')->assertOk()->assertSee("Confirm it's you");

        $this->post('/user/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/user/confirm-password', ['password' => 'password'])->assertRedirect('/account/google');
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_confirmed_employee_can_connect_google_without_changing_their_role(): void
    {
        $user = User::factory()->create(['role' => 'agent']);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('with')->once()->with(['prompt' => 'select_account'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.com/example'));
        $provider->shouldReceive('user')->once()->andReturn($this->googleUser('linked-agent-id', $user->email));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/account/google')
            ->assertRedirect('https://accounts.google.com/example')->assertSessionHas('google_link_user_id', $user->id);
        $this->get('/auth/google/callback')->assertRedirect(route('account'))->assertSessionMissing('google_link_user_id');
        $this->assertSame('linked-agent-id', $user->fresh()->google_id);
        $this->assertSame(UserRole::Agent, $user->fresh()->role);
        $this->get('/account')->assertSee('Your Google account is connected');
    }

    public function test_linking_a_different_email_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->mockGoogleUser('different-email-id', 'different@example.com');

        $this->actingAs($user)->withSession(['google_link_user_id' => $user->id])->get('/auth/google/callback')
            ->assertRedirect(route('account'))->assertSessionHasErrors('google');
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_linking_a_google_identity_already_used_by_another_account_is_rejected(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['google_id' => 'already-connected']);
        $this->mockGoogleUser('already-connected', $user->email);

        $this->actingAs($user)->withSession(['google_link_user_id' => $user->id])->get('/auth/google/callback')
            ->assertRedirect(route('account'))->assertSessionHasErrors('google');
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_a_link_callback_cannot_connect_another_users_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->mockGoogleUser('forged-link-id', $other->email);

        $this->actingAs($user)->withSession(['google_link_user_id' => $other->id])->get('/auth/google/callback')->assertForbidden();
        $this->assertNull($user->fresh()->google_id);
        $this->assertNull($other->fresh()->google_id);
    }

    public function test_a_sign_in_callback_does_not_switch_an_authenticated_browser_to_another_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['google_id' => 'other-google-id']);
        $this->mockGoogleUser('other-google-id', $other->email);

        $this->actingAs($user)->get('/auth/google/callback')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    private function mockGoogleUser(string $id, string $email, bool $verified = true): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($this->googleUser($id, $email, $verified));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function googleUser(string $id, string $email, bool $verified = true): GoogleUser
    {
        return (new GoogleUser)->setRaw(['email_verified' => $verified])->map([
            'id' => $id, 'email' => $email, 'name' => 'Google Buyer',
        ]);
    }
}
