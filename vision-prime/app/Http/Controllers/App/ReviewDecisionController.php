<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Ai\Actions\DecideReviewItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewDecisionController extends Controller
{
    public function store(Request $request, int $review, DecideReviewItem $decision): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected,changes_requested'], 'note' => ['nullable', 'string', 'max:2000']]);
        $decision->handle($review, $request->user(), $data['decision'], $data['note'] ?? null);

        return back()->with('status', 'تصمیم بررسی ثبت شد.');
    }
}
