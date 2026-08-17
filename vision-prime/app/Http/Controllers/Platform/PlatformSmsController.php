<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Platform\Services\SmsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSmsController
{
    public function __construct(
        private readonly SmsManager $sms,
        private readonly RecordAuditLog $audit,
    ) {}

    public function index(): Response
    {
        $logs = DB::table('sms_logs')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'driver' => $row->driver,
                'to' => $row->to,
                'message' => $row->message,
                'status' => $row->status,
                'error' => $row->error,
                'created_at' => $row->created_at,
            ]);

        return Inertia::render('Platform/Sms', [
            'providers' => $this->sms->options(),
            'logs' => $logs,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'message' => ['required', 'string', 'max:1000'],
            'driver' => ['required', 'string'],
        ]);

        $result = $this->sms->send($data['to'], $data['message'], $data['driver']);

        $this->audit->handle(
            action: 'platform.sms.sent',
            metadata: ['to' => $data['to'], 'driver' => $data['driver'], 'success' => $result['success']],
        );

        return $result['success']
            ? back()->with('success', 'پیامک با موفقیت ارسال شد.')
            : back()->with('error', 'ارسال پیامک ناموفق بود: '.($result['error'] ?? 'خطای نامشخص'));
    }
}
