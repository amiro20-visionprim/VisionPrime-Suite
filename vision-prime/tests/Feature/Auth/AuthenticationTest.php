<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_for_app_routes(): void
    {
        $this->get('/app/dashboard')->assertRedirect(route('login'));
    }

    public function test_a_user_can_log_in_and_is_redirected_to_the_app(): void
    {
        $user = User::factory()->create([
            'email' => 'team@visionprime.test',
            'password' => Hash::make('StrongPassword2026'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'StrongPassword2026',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('app.dashboard'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_succeeded',
            'actor_id' => $user->getKey(),
        ]);
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('app.dashboard'));
    }

    public function test_a_password_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'owner@visionprime.test']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
