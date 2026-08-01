<?php

declare(strict_types=1);

namespace App\Domains\Ai\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\Crypt;

class SaveAiProviderSetting
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(Organization $org, string $provider, array $config): void
    {
        \DB::table('ai_provider_settings')->updateOrInsert(['organization_id' => $org->id, 'provider' => $provider], ['encrypted_config' => Crypt::encryptString(json_encode($config)), 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]);
        $this->audit->handle(action: 'ai.provider_setting_saved', organization: $org, after: ['provider' => $provider]);
    }
}
