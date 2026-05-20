<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = min((int) $request->query('per_page', 20), 50);
        $unreadOnly = $request->boolean('unread_only');

        $q = Notification::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $q->whereNull('leida_at');
        }

        return response()->json($q->paginate($perPage));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('leida_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->count();
        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No encontrada.'], 404);
        }
        if (! $notification->leida_at) {
            $notification->update(['leida_at' => now()]);
        }
        return response()->json($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('leida_at')
            ->update(['leida_at' => now()]);
        return response()->json(['marked' => $count]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No encontrada.'], 404);
        }
        $notification->delete();
        return response()->json(['ok' => true]);
    }
}
