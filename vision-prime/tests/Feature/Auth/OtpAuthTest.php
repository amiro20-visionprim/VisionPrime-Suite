<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Identity\Services\OtpService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_sends_code_and_returns_sandbox_code(): void
    {
        $user = User::factory()->create([
            'email' => 'otp@visionprime.test',
            'phone' => '09123456789',
            'phone_verified_at' => now(),
            'password' => Hash::make('StrongPassword2026'),
        ]);

        $response = $this->postJson('/login/otp', ['phone' => '09123456789']);

        $response->assertOk()->assertJson(['sent' => true]);
        $this->assertNotEmpty($response->json('code'));
        $this->assertTrue(Cache::has(OtpService::cacheKey('09123456789', 'login')));
    }

    public function test_otp_verify_logs_user_in(): void
    {
        $user = User::factory()->create([
            'email' => 'otp2@visionprime.test',
            'phone' => '09120000001',
            'phone_verified_at' => now(),
            'password' => Hash::make('StrongPassword2026'),
        ]);

        $result = $this->postJson('/login/otp', ['phone' => '09120000001']);
        $result->assertOk()->assertJson(['sent' => true]);
        $code = (string) $result->json('code');

        $verify = $this->postJson('/login/otp/verify', [
            'phone' => '09120000001',
            'code' => $code,
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_otp_verify_rejects_wrong_code(): void
    {
        User::factory()->create([
            'email' => 'otp3@visionprime.test',
            'phone' => '09120000002',
            'phone_verified_at' => now(),
            'password' => Hash::make('StrongPassword2026'),
        ]);

        $this->postJson('/login/otp', ['phone' => '09120000002']);

        $verify = $this->postJson('/login/otp/verify', [
            'phone' => '09120000002',
            'code' => '000000',
        ]);

        $verify->assertStatus(422);
        $this->assertGuest();
    }

    public function test_register_otp_requests_code_for_new_phone(): void
    {
        $response = $this->postJson('/register/otp', ['phone' => '09350000000']);

        $response->assertOk()->assertJson(['sent' => true]);
        $this->assertNotEmpty($response->json('code'));
    }

    public function test_register_requires_phone_otp_and_terms(): void
    {
        $request = $this->postJson('/register/otp', ['phone' => '09351111111']);
        $code = (string) $request->json('code');

        // بدون terms و کد → خطای اعتبارسنجی
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'new@visionprime.test',
            'phone' => '09351111111',
            'otp_code' => $code,
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'terms' => false,
        ])->assertSessionHasErrors('terms');

        // با کد و terms → موفق
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'new@visionprime.test',
            'phone' => '09351111111',
            'otp_code' => $code,
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'terms' => true,
        ])->assertRedirect(route('app.onboarding'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'new@visionprime.test',
            'phone' => '09351111111',
        ]);
    }

    public function test_register_rejects_used_phone(): void
    {
        User::factory()->create([
            'email' => 'exists@visionprime.test',
            'phone' => '09352222222',
            'phone_verified_at' => now(),
        ]);

        $this->postJson('/register/otp', ['phone' => '09352222222'])
            ->assertStatus(422);
    }
}
