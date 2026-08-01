<?php

declare(strict_types=1);

namespace App\Domains\Connector\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Site;

class DisconnectSite
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(Site $site): void
    {
        \DB::table('site_connections')->where('site_id', $site->id)->update(['status' => 'disconnected', 'secret_ciphertext' => null, 'last_seen_at' => null, 'health' => null, 'updated_at' => now()]);
        \DB::table('connector_nonces')->whereIn('site_connection_id', fn ($q) => $q->select('id')->from('site_connections')->where('site_id', $site->id))->delete();
        $this->audit->handle(action: 'connector.disconnected', subject: $site);
    }
}
