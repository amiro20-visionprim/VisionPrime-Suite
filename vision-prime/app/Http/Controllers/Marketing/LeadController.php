<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domains\Marketing\Actions\ScoreLead;
use App\Domains\Marketing\Services\NotifyMarketingTeam;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'message' => ['nullable', 'string', 'max:4000'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'landing_page' => ['nullable', 'string', 'max:500'],
        ]);

        $lead = Lead::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
            'website' => $data['website'] ?? null,
            'message' => $data['message'] ?? null,
            'source' => 'demo',
            'status' => 'new',
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'landing_page' => $data['landing_page'] ?? null,
            'referrer' => $this->normalizeReferrer($request),
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'locale' => $request->getLocale(),
                'device' => $this->detectDevice($request->userAgent()),
            ],
        ]);

        app(ScoreLead::class)->handle($lead);
        app(NotifyMarketingTeam::class)->handle($lead);

        return back()->with('status', 'درخواست شما ثبت شد؛ تیم ما در کمتر از ۲۴ ساعت کاری برای هماهنگی دمو با شما تماس می‌گیرد.');
    }

    private function normalizeReferrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if ($referrer === null || $referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (is_string($host) && str_contains($host, 'visionprime')) {
            return 'internal';
        }

        return str_starts_with($referrer, 'http') ? $referrer : null;
    }

    private function detectDevice(?string $userAgent): string
    {
        if ($userAgent === null) {
            return 'unknown';
        }

        if (stripos($userAgent, 'mobile') !== false || stripos($userAgent, 'android') !== false || stripos($userAgent, 'iphone') !== false) {
            return 'mobile';
        }

        return 'desktop';
    }
}
