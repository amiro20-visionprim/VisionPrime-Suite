<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Platform\Services\TriageEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformDecisionController extends Controller
{
    public function __construct(private readonly TriageEngine $triage) {}

    public function resolve(Request $request, int $event): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->triage->resolve(
            $event,
            $request->user()?->getKey(),
            (string) ($data['note'] ?? ''),
        );

        return back()->with('status', 'تصمیم ثبت و بسته شد.');
    }
}
