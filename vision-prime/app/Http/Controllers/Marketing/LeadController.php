<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

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
        ]);

        Lead::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
            'website' => $data['website'] ?? null,
            'message' => $data['message'] ?? null,
            'source' => 'demo',
            'status' => 'new',
        ]);

        return back()->with('status', 'درخواست شما ثبت شد؛ برای هماهنگی دمو بهزودی با شما تماس می‌گیریم.');
    }
}
