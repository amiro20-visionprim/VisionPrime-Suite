<?php

declare(strict_types=1);

namespace App\Domains\Ai\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Site;

class CreateAiGeneration
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(Site $site, ?int $templateId, array $input, array $output, array $usage = []): int
    {
        $generation = \DB::table('ai_generations')->insertGetId(['site_id' => $site->id, 'template_id' => $templateId, 'input_redacted' => json_encode($input), 'output_status' => 'needs_review', 'usage' => json_encode($usage), 'created_at' => now(), 'updated_at' => now()]);
        $version = \DB::table('ai_generation_versions')->insertGetId(['generation_id' => $generation, 'version' => 1, 'output' => json_encode($output), 'status' => 'needs_review', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('ai_generations')->where('id', $generation)->update(['current_version_id' => $version]);
        $this->audit->handle(action: 'ai.generation_created', subject: $site, after: ['generation_id' => $generation]);

        return $generation;
    }
}
