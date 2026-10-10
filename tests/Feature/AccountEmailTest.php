<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_mixed_case_email_can_log_in_and_reset_its_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ExistingBuyer@Example.com']);

        $this->post('/login', ['email' => 'existingbuyer@example.com', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $this->post('/forgot-password', ['email' => 'EXISTINGBUYER@example.com'])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'email' => 'existingbuyer@example.com', 'token' => $token,
            'password' => 'a-new-account-password', 'password_confirmation' => 'a-new-account-password',
        ])->assertRedirect('/login');
        $this->assertTrue(Hash::check('a-new-account-password', $user->fresh()->password));
        $this->assertSame('ExistingBuyer@Example.com', $user->fresh()->email);
    }

    public function test_public_registration_cannot_duplicate_a_legacy_mixed_case_email(): void
    {
        User::factory()->create(['email' => 'ExistingBuyer@Example.com']);

        $this->post('/register', [
            'name' => 'Duplicate', 'email' => 'existingbuyer@example.com',
            'password' => 'a-long-buyer-password', 'password_confirmation' => 'a-long-buyer-password',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_employee_registration_cannot_duplicate_a_legacy_mixed_case_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'ExistingBuyer@Example.com']);

        $this->actingAs($admin)->post('/employees', [
            'name' => 'Duplicate', 'email' => 'existingbuyer@example.com', 'role' => 'agent',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_database_uniqueness_prevents_case_variants_even_without_form_validation(): void
    {
        User::factory()->create(['email' => 'ExistingBuyer@Example.com']);
        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['email' => 'existingbuyer@example.com']);
    }

    public function test_email_index_migration_refuses_existing_duplicates_without_changing_accounts(): void
    {
        DB::statement('DROP INDEX users_email_lower_unique');
        User::factory()->create(['email' => 'ExistingBuyer@Example.com']);
        User::factory()->create(['email' => 'existingbuyer@example.com']);
        $migration = require database_path('migrations/2026_10_10_100000_enforce_case_insensitive_user_emails.php');

        try {
            $migration->up();
            $this->fail('Duplicate emails should block index creation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No accounts have been changed', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['email' => 'ExistingBuyer@Example.com']);
        $this->assertDatabaseHas('users', ['email' => 'existingbuyer@example.com']);
    }

    public function test_malformed_email_inputs_produce_validation_errors_instead_of_server_errors(): void
    {
        foreach (['/register', '/login', '/forgot-password', '/reset-password'] as $path) {
            $this->postJson($path, ['email' => ['invalid-input'], 'password' => 'a-long-password'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->assertDatabaseCount('users', 0);
    }
}
