<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/v1/notifications — ouvert à tout utilisateur authentifié, scopé au sien
    public function index(Request $request): JsonResponse
    {
        $notifications = AppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($notifications);
    }

    // GET /api/v1/notifications/unread-count
    public function unreadCount(Request $request): JsonResponse
    {
        $count = AppNotification::where('user_id', $request->user()->id)->unread()->count();

        return response()->json(['count' => $count]);
    }

    // PATCH /api/v1/notifications/{notification}/read
    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marquée lue', 'notification' => $notification]);
    }

    // PATCH /api/v1/notifications/read-all
    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications marquées lues']);
    }
}
