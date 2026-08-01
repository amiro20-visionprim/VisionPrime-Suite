<?php

declare(strict_types=1);

namespace App\Domains\Audit\Actions;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordAuditLog
{
    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?Organization $organization = null,
        string $source = 'web',
    ): AuditLog {
        $request = request();
        $actor = $request instanceof Request ? $request->user() : null;
        $organization ??= app(CurrentOrganization::class)->has() ? app(CurrentOrganization::class)->get() : null;

        return AuditLog::query()->create([
            'organization_id' => $organization?->getKey(),
            'actor_id' => $actor instanceof User ? $actor->getKey() : null,
            'actor_type' => $actor instanceof User ? 'user' : null,
            'action' => $action,
            'subject_type' => $subject === null ? null : Str::kebab(class_basename($subject)),
            'subject_id' => $subject?->getKey(),
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'metadata' => $this->redact($metadata),
            'source' => $source,
            'request_id' => app(RequestContext::class)->requestId(),
            'ip_hash' => $request instanceof Request ? $this->hashIp($request->ip()) : null,
            'occurred_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'authorization', 'api_key', 'cookie'];

        foreach ($payload as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            if (collect($sensitiveKeys)->contains(fn (string $sensitiveKey): bool => Str::contains($normalizedKey, $sensitiveKey))) {
                $payload[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }

        return $payload;
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
