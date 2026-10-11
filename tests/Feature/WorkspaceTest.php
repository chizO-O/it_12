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

        $response->assertOk()
            ->assertSee($expectedTool)
            ->assertDontSee($excludedTool)
            ->assertSee('Workspace navigation')
            ->assertSee('Mobile navigation');

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $links = [];
        foreach ($xpath->query('//nav[@aria-label="Workspace navigation"]//a') as $link) {
            $links[] = $link->getAttribute('href');
        }

        // Expected URLs are listed independently of config/workspace.php, so this
        // test catches accidental additions to the menu (not just missing items).
        $byRole = [
            'buyer' => ['inventory.index', 'inquiries.index', 'reservations.index', 'cts.index', 'sales.index'],
            'agent' => ['inventory.index', 'inquiries.index', 'reservations.index'],
            'admin' => ['inventory.index', 'employees.index', 'cts.index', 'reports.index'],
            'unit_manager' => ['inventory.index', 'agents.index', 'assignments.index', 'reservations.index', 'sales.index', 'reports.index'],
            'field_planner' => ['properties.index', 'cts.index'],
            'cashier' => ['sales.index'],
        ];
        $expected = array_map('route', array_merge(
            [$user->role->dashboardRoute(), 'notifications.index', 'account'],
            $byRole[$role],
        ));

        $links = array_values(array_unique($links));
        sort($links);
        sort($expected);
        $this->assertSame($expected, $links);
        
    }

    
public function test_agent_navigation_has_one_conversations_link_without_duplicate_messages_item(): void
{
    $agent = User::factory()->create([
        'role' => UserRole::Agent,
    ]);

    $response = $this->actingAs($agent)
        ->get(route('agent.dashboard'))
        ->assertOk();

    $document = new DOMDocument;

    @$document->loadHTML($response->getContent());

    $xpath = new DOMXPath($document);

    // GroveMap renders separate desktop and mobile menus.
    $menus = $xpath->query(
        '//nav[@aria-label="Workspace navigation"]'
    );

    $this->assertGreaterThan(0, $menus->length);

    foreach ($menus as $menu) {
        $conversationLinks = $xpath->query(
            './/a[normalize-space()="Conversations"]',
            $menu
        );

        // Each menu should contain exactly one Conversations link.
        $this->assertSame(1, $conversationLinks->length);

        $this->assertSame(
            route('inquiries.index'),
            $conversationLinks->item(0)->getAttribute('href')
        );

        // No separate Inquiries or Messages links.
        $this->assertSame(
            0,
            $xpath->query(
                './/a[normalize-space()="Messages" or normalize-space()="Inquiries"]',
                $menu
            )->length
        );
    }

    $this->actingAs($agent)
        ->get(route('inquiries.index'))
        ->assertOk()
        ->assertSee('Conversations');
}


    public static function roles(): array
    {
        return [
            ['buyer', 'Property listings', 'Employee management'],
            ['agent', 'Conversations', 'Sales reports'],
            ['unit_manager', 'Reservation reviews', 'Employee management'],
            ['field_planner', 'Property management', 'Sales reports'],
            ['admin', 'Employee management', 'My payments'],
            ['cashier', 'Transactions & payments', 'Reservation reviews'],
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
            'name' => 'Updated Name',
            'user_id' => $other->id,
            'email' => 'forged@example.com',
            'password' => 'forged-password',
            'role' => 'admin',
            'google_id' => 'forged-google-id',
            'email_verified_at' => null,
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
        return array_map(fn(UserRole $role) => [$role->value], UserRole::cases());
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
