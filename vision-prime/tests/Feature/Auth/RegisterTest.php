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
        $request = $this->postJson('/register/otp', ['phone' => '09350000123']);
        $code = (string) $request->json('code');

        $response = $this->post('/register', [
            'name' => 'کاربر جدید',
            'email' => 'new.user@example.ir',
            'phone' => '09350000123',
            'otp_code' => $code,
            'password' => 'StrongPass@2026',
            'password_confirmation' => 'StrongPass@2026',
            'terms' => true,
        ]);

        $response->assertRedirect('/app/onboarding');

        $this->assertDatabaseHas('users', ['email' => 'new.user@example.ir']);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.registered']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.ir']);

        $request = $this->postJson('/register/otp', ['phone' => '09350000124']);
        $code = (string) $request->json('code');

        $this->post('/register', [
            'name' => 'کاربر',
            'email' => 'taken@example.ir',
            'phone' => '09350000124',
            'otp_code' => $code,
            'password' => 'StrongPass@2026',
            'password_confirmation' => 'StrongPass@2026',
            'terms' => true,
        ])->assertSessionHasErrors('email');
    }

    public function test_weak_password_is_rejected(): void
    {
        $request = $this->postJson('/register/otp', ['phone' => '09350000125']);
        $code = (string) $request->json('code');

        $this->post('/register', [
            'name' => 'کاربر',
            'email' => 'weak@example.ir',
            'phone' => '09350000125',
            'otp_code' => $code,
            'password' => 'short',
            'password_confirmation' => 'short',
            'terms' => true,
        ])->assertSessionHasErrors('password');
    }
}
