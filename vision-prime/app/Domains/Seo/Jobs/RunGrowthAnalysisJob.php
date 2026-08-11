<?php

declare(strict_types=1);

namespace App\Domains\Seo\Jobs;

use App\Domains\Seo\Actions\RunGrowthAnalysis;
use App\Domains\Workspace\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunGrowthAnalysisJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 3;

    public function __construct(public int $siteId) {}

    public function handle(RunGrowthAnalysis $analysis): void
    {
        $site = Site::find($this->siteId);

        if ($site === null) {
            return;
        }

        $analysis->handle($site);
    }
}
