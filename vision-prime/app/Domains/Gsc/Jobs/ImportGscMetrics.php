<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Jobs;

use App\Domains\Gsc\Actions\UpsertGscMetric;
use App\Domains\Gsc\Services\GscMetricsClient;
use App\Domains\Seo\Jobs\RunGrowthAnalysisJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportGscMetrics implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public int $importRunId) {}

    public function handle(GscMetricsClient $client, UpsertGscMetric $upsert): void
    {
        $run = \DB::table('gsc_import_runs')->where('id', $this->importRunId)->firstOrFail();
        $property = \DB::table('gsc_properties')->where('id', $run->gsc_property_id)->firstOrFail();
        $account = \DB::table('gsc_accounts')->where('id', $property->gsc_account_id)->firstOrFail();
        \DB::table('gsc_import_runs')->where('id', $run->id)->update(['status' => 'running', 'started_at' => now()]);
        try {
            $count = 0;
            foreach ([['page', 'page'], ['query', 'query'], ['query,page', 'queryPage'], ['date,hour', 'hour']] as [$dimensions,$method]) {
                foreach (($client->query($account, $property->property_uri, $run->date_start, $run->date_end, explode(',', $dimensions))['rows'] ?? []) as $row) {
                    $upsert->{$method}($property->id, $run->date_end, $row);
                    $count++;
                }
            }\DB::table('gsc_import_runs')->where('id', $run->id)->update(['status' => 'completed', 'summary' => json_encode(['rows' => $count]), 'finished_at' => now()]);

            // A fresh dataset means the intelligence layer must be rebuilt.
            RunGrowthAnalysisJob::dispatch((int) $property->site_id);
        } catch (\Throwable $e) {
            \DB::table('gsc_import_runs')->where('id', $run->id)->update(['status' => 'failed', 'error' => json_encode(['message' => $e->getMessage()]), 'finished_at' => now()]);
            throw $e;
        }
    }
}
