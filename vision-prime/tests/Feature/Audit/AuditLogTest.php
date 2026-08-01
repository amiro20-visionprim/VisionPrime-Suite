<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_organization_creation_is_audited_with_request_context(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/app/onboarding', ['name' => 'آژانس ممیزی']);

        $organization = Organization::query()->where('name', 'آژانس ممیزی')->firstOrFail();
        $auditLog = AuditLog::query()->where('action', 'organization.created')->firstOrFail();

        $response->assertHeader('X-Request-ID');
        $this->assertSame($organization->getKey(), $auditLog->organization_id);
        $this->assertSame($user->getKey(), $auditLog->actor_id);
        $this->assertSame($organization->getKey(), $auditLog->subject_id);
        $this->assertNotNull($auditLog->request_id);
        $this->assertNotNull($auditLog->ip_hash);
        $this->assertSame('آژانس ممیزی', $auditLog->after['name']);
    }

    public function test_sensitive_values_are_redacted_before_audit_storage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $auditLog = app(RecordAuditLog::class)->handle(
            action: 'test.sensitive_payload',
            subject: $user,
            after: ['token' => 'do-not-store', 'nested' => ['api_key' => 'do-not-store']],
        );

        $this->assertSame('[REDACTED]', $auditLog->after['token']);
        $this->assertSame('[REDACTED]', $auditLog->after['nested']['api_key']);
    }
}
