<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get unread notifications for authenticated user
     */
    public function getUnread(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $user = Auth::user();

        // If no user is authenticated

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

         $userId = $user->id; // ✅ define userId from authenticated user

        $notifications = $this->notificationService->getUnreadNotifications($userId, $limit);
        $unreadCount = $this->notificationService->countUnreadNotifications($userId);

        // Format notifications for frontend
        $formattedNotifications = $notifications->map(function ($notif) {
            return [
                'id' => $notif->id,
                'user_notif_id' => $notif->id,
                'read' => !is_null($notif->read_at),
                'title' => $notif->notification->title ?? '',
                'message' => $notif->notification->message ?? '',
                'time' => $notif->created_at->diffForHumans(),
                'type' => $notif->notification->type ?? 'info',
                'icon_type' => $notif->notification->icon_type ?? 'info',
                'action_url' => $notif->notification->full_action_url ?? '',
            ];
        });

        return response()->json([
            'success' => true,
            'notifications' => $formattedNotifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Get all notifications for authenticated user
     */
    public function getAll(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);
        $user = Auth::user();
        
        // If no user is authenticated
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
         $userId = $user->id; // ✅ define userId from authenticated user

        $notifications = $this->notificationService->getAllNotifications($userId, $limit, $offset);
        $unreadCount = $this->notificationService->countUnreadNotifications($userId);

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'notification_id' => 'required|integer',
        ]);

        $user = Auth::user();
        
        // If no user is authenticated
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
         $userId = $user->id; // ✅ define userId from authenticated user
        $this->notificationService->markAsRead($request->notification_id, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'unread_count' => $this->notificationService->countUnreadNotifications($userId),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        
        // If no user is authenticated
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
         $userId = $user->id; // ✅ define userId from authenticated user
        $this->notificationService->markAllAsRead($userId);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification
     */
    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'notification_id' => 'required|integer',
        ]);

        $user = Auth::user();
        
        // If no user is authenticated
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
         $userId = $user->id; // ✅ define userId from authenticated user
        $this->notificationService->deleteNotification($request->notification_id, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Delete all notifications
     */
    public function deleteAll(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // If no user is authenticated
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
         $userId = $user->id; // ✅ define userId from authenticated user
        $this->notificationService->deleteAllNotifications($userId);

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted',
        ]);
    }

    /**
     * Get notification count
     */
    public function getCount(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // If no user is authenticated
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

         $userId = $user->id; // ✅ define userId from authenticated user
        $unreadCount = $this->notificationService->countUnreadNotifications($userId);

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
    }
}
