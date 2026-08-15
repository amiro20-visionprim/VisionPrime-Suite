<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Gsc\Actions\UpsertGscMetric;
use App\Domains\Gsc\Jobs\ImportGscMetrics;
use App\Domains\Gsc\Services\GscMetricsClient;
use Illuminate\Console\Command;

class ImportGscCommand extends Command
{
    protected $signature = 'gsc:import
        {--site= : Import only this site id}
        {--days=28 : Number of days to look back}
        {--sync : Run synchronously instead of queueing (requires no queue worker)}';

    protected $description = 'Import Search Console metrics (clicks/impressions/position) for every site with a connected GSC property';

    public function handle(): int
    {
        $siteId = $this->option('site');
        $days = max(1, (int) $this->option('days'));
        $sync = filter_var($this->option('sync'), FILTER_VALIDATE_BOOL);

        $query = \DB::table('gsc_properties')
            ->join('sites', 'sites.id', '=', 'gsc_properties.site_id')
            ->select('gsc_properties.*', 'sites.name as site_name');

        if ($siteId !== null) {
            $query->where('gsc_properties.site_id', (int) $siteId);
        }

        $properties = $query->get();
        if ($properties->isEmpty()) {
            $this->warn('هیچ ملک سرچ کنسولی متصل نیست.');

            return self::SUCCESS;
        }

        $dateEnd = now()->toDateString();
        $dateStart = now()->subDays($days)->toDateString();
        $queued = 0;
        $synced = 0;
        $failed = 0;

        foreach ($properties as $property) {
            $runId = \DB::table('gsc_import_runs')->insertGetId([
                'gsc_property_id' => $property->id,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'status' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($sync) {
                try {
                    app(ImportGscMetrics::class, ['importRunId' => $runId])->handle(
                        app(GscMetricsClient::class),
                        app(UpsertGscMetric::class),
                    );
                    $synced++;
                    $this->line("  ✓ {$property->site_name} — {$property->property_uri} ({$dateStart} تا {$dateEnd})");
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("  ✗ {$property->site_name} — {$property->property_uri}: {$e->getMessage()}");
                }

                continue;
            }

            ImportGscMetrics::dispatch($runId);
            $queued++;
            $this->line("  [{$queued}] {$property->site_name} — {$property->property_uri} ({$dateStart} تا {$dateEnd})");
        }

        if ($sync) {
            $this->info("{$synced} ایمپورت همگام انجام شد، {$failed} ناموفق.");

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info("{$queued} import در صف قرار گرفت.");

        return self::SUCCESS;
    }
}
