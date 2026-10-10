<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_role_navigation_exposes_only_its_authorized_working_pages(string $role, string $expectedTool, string $excludedTool): void
    {
        $user = User::factory()->create(['role' => $role]);
        $response = $this->actingAs($user)->get(route($user->role->dashboardRoute()));

        $response->assertOk()->assertSee($expectedTool)->assertDontSee($excludedTool)
            ->assertSee('Workspace navigation')->assertSee('Mobile navigation')->assertSee('Coming soon');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $links = [];
        foreach ($xpath->query('//nav[@aria-label="Workspace navigation"]//a') as $link) {
            $links[] = $link->getAttribute('href');
        }

        $expected = [route($user->role->dashboardRoute()), route('account'), route('inventory.index')];
        if ($user->role === UserRole::FieldPlanner) {
            $expected[] = route('properties.index');
        }
        if ($user->role === UserRole::Admin) {
            $expected[] = route('employees.index');
        }

        $links = array_values(array_unique($links));
        sort($links);
        sort($expected);
        $this->assertSame($expected, $links);
        $this->assertGreaterThan(0, $xpath->query('//nav[@aria-label="Workspace navigation"]//span[@aria-disabled="true"]')->length);
    }

    public static function roles(): array
    {
        return [
            ['buyer', 'Browse lots', 'Payment records'],
            ['agent', 'My buyers', 'CTS documents'],
            ['unit_manager', 'Reservation reviews', 'Payment records'],
            ['field_planner', 'Properties & lots', 'CTS documents'],
            ['admin', 'Employees', 'My buyers'],
            ['cashier', 'Payment records', 'Reservation reviews'],
        ];
    }

    #[DataProvider('allRoles')]
    public function test_profile_updates_only_the_signed_in_users_name(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $other = User::factory()->create();
        $originalEmail = $user->email;
        $originalPassword = $user->password;

        $this->actingAs($user)->patch('/account', [
            'name' => 'Updated Name', 'user_id' => $other->id,
            'email' => 'forged@example.com', 'password' => 'forged-password',
            'role' => 'admin', 'google_id' => 'forged-google-id', 'email_verified_at' => null,
        ])->assertRedirect(route('account'))->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame(UserRole::from($role), $user->role);
        $this->assertSame($originalEmail, $user->email);
        $this->assertSame($originalPassword, $user->password);
        $this->assertNull($user->google_id);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame($other->name, $other->fresh()->name);
        $this->get('/account')->assertOk()->assertSee('Updated Name');
    }

    public static function allRoles(): array
    {
        return array_map(fn (UserRole $role) => [$role->value], UserRole::cases());
    }

    public function test_profile_update_rejects_missing_or_long_names(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['', str_repeat('a', 256)] as $name) {
            $this->patch('/account', ['name' => $name])->assertSessionHasErrors('name');
        }

        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_guests_and_unverified_users_cannot_update_profiles(): void
    {
        $this->patch('/account', ['name' => 'Forged'])->assertRedirect(route('login'));
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->patch('/account', ['name' => 'Forged'])->assertRedirect(route('verification.notice'));
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_profile_names_are_escaped_in_both_profile_and_navigation(): void
    {
        $user = User::factory()->create();
        $name = '<script>alert(1)</script>';
        $this->actingAs($user)->patch('/account', ['name' => $name])->assertRedirect(route('account'));

        $this->get('/account')->assertSee($name)->assertDontSee($name, false);
        $this->get('/buyer/dashboard')->assertSee($name)->assertDontSee($name, false);
    }
}
