<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Gsc\Jobs\ImportGscMetrics;
use Illuminate\Console\Command;

class ImportGscCommand extends Command
{
    protected $signature = 'gsc:import
        {--site= : Import only this site id}
        {--days=28 : Number of days to look back}';

    protected $description = 'Queue a Search Console import for every site with a connected GSC property';

    public function handle(): int
    {
        $siteId = $this->option('site');
        $days = max(1, (int) $this->option('days'));

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

        foreach ($properties as $property) {
            $runId = \DB::table('gsc_import_runs')->insertGetId([
                'gsc_property_id' => $property->id,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'status' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ImportGscMetrics::dispatch($runId);
            $queued++;
            $this->line("  [{$queued}] {$property->site_name} — {$property->property_uri} ({$dateStart} تا {$dateEnd})");
        }

        $this->info("{$queued} import در صف قرار گرفت.");

        return self::SUCCESS;
    }
}
