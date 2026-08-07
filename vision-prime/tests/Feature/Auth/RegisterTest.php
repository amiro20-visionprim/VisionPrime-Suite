<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_can_see_register_page(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_registration_creates_user_and_redirects_to_onboarding(): void
    {
        $response = $this->post('/register', [
            'name' => 'کاربر جدید',
            'email' => 'new.user@example.ir',
            'password' => 'StrongPass@2026',
            'password_confirmation' => 'StrongPass@2026',
        ]);

        $response->assertRedirect('/app/onboarding');

        $this->assertDatabaseHas('users', ['email' => 'new.user@example.ir']);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.registered']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.ir']);

        $this->post('/register', [
            'name' => 'کاربر',
            'email' => 'taken@example.ir',
            'password' => 'StrongPass@2026',
            'password_confirmation' => 'StrongPass@2026',
        ])->assertSessionHasErrors('email');
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->post('/register', [
            'name' => 'کاربر',
            'email' => 'weak@example.ir',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
