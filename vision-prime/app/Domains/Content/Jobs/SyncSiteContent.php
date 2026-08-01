<?php

declare(strict_types=1);

namespace App\Domains\Content\Jobs;

use App\Domains\Connector\Contracts\ConnectorContentClient;
use App\Domains\Content\Actions\UpsertUrlProfile;
use App\Domains\Content\Models\SyncRun;
use App\Domains\Content\Models\SyncRunItem;
use App\Domains\Workspace\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSiteContent implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public int $syncRunId) {}

    public function handle(ConnectorContentClient $client, UpsertUrlProfile $upsert): void
    {
        $run = SyncRun::findOrFail($this->syncRunId);
        $site = Site::findOrFail($run->site_id);
        $connection = \DB::table('site_connections')->where('site_id', $site->id)->where('status', 'connected')->firstOrFail();
        $run->update(['status' => 'running', 'started_at' => now()]);
        try {
            $page = 1;
            $count = 0;
            do {
                $data = $client->get($connection, '/vision-prime/v1/content', ['page' => $page, 'per_page' => 50]);
                foreach ($data['data'] as $item) {
                    $profile = $upsert->handle($site, $item);
                    SyncRunItem::updateOrCreate(['sync_run_id' => $run->id, 'external_id' => (string) $item['id']], ['url' => $item['url'], 'status' => 'completed', 'action' => 'upserted']);
                    $count++;
                }$page++;
            } while ($page <= (int) ($data['total_pages'] ?? 0));
            $run->update(['status' => 'completed', 'summary' => ['items' => $count], 'finished_at' => now()]);
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error' => ['message' => $e->getMessage()], 'finished_at' => now()]);
            throw $e;
        }
    }
}
