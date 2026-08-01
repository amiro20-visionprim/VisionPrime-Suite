<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Actions;

use App\Domains\Workspace\Models\Site;

class CreateImpactEvent
{
    public function handle(Site $site, string $sourceType, ?int $sourceId, array $baseline, string $note): int
    {
        return \DB::table('impact_events')->insertGetId(['site_id' => $site->id, 'source_type' => $sourceType, 'source_id' => $sourceId, 'baseline' => json_encode($baseline), 'attribution_note' => $note, 'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
