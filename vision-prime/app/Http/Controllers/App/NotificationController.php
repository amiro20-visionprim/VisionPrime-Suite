<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->limit(20)
            ->get()
            ->map(function ($notification): array {
                $data = $notification->data;

                return [
                    'id' => $notification->getKey(),
                    'read' => $notification->read(),
                    'leadId' => $data['lead_id'] ?? null,
                    'leadName' => $data['lead_name'] ?? 'لید جدید',
                    'source' => $data['source'] ?? null,
                    'score' => $data['score'] ?? null,
                    'campaign' => $data['campaign'] ?? null,
                    'createdAt' => $notification->created_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()
            ->notifications()
            ->whereKey($notification)
            ->get()
            ->each->markAsRead();

        return response()->json([
            'ok' => true,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'unreadCount' => 0]);
    }
}
