<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChNotificationUser;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Notifications
 * Endpoints for notification management including read/unread status
 */
class NotificationPageApiController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;

       
    }

    /**
     * Get all notifications (paginated)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }
       
        $notifications = ChNotificationUser::with('notification')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $notifications,
        ]);
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(int $notificationUser_id)
    {
        \Log::info('markAsRead called', ['notificationUser_id' => $notificationUser_id]);

        $user = Auth::user();
        \Log::info('Auth check', ['user' => $user ? $user->id : null]);

        if (!$user) {
            \Log::warning('Unauthorized - no user', ['notificationUser_id' => $notificationUser_id]);
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $notificationUser = ChNotificationUser::find($notificationUser_id);
        \Log::info('Notification lookup', ['found' => $notificationUser ? true : false, 'notificationUser_id' => $notificationUser_id]);

        if (!$notificationUser) {
            return response()->json(['status' => 'error', 'message' => 'Notification not found'], 404);
        }

        \Log::info('Ownership check', ['notification_user_id' => $notificationUser->user_id, 'auth_user_id' => $user->id]);

        if ((int)$notificationUser->user_id !== (int)$user->id) {
            \Log::warning('Unauthorized - ownership mismatch', ['notification_user_id' => $notificationUser->user_id, 'auth_user_id' => $user->id]);
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if (!$notificationUser->read_at) {
            $notificationUser->update(['read_at' => now()]);
        }

        \Log::info('Notification marked as read', ['notificationUser_id' => $notificationUser_id, 'user_id' => $user->id]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $this->notificationService->markAllAsRead($user->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Get unread notifications
     */
    public function unread(Request $request)
    {
        $user  = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }
        $limit = $request->get('limit', 10);

        $notifications = $this->notificationService->getUnreadNotifications($user->id, $limit);
        $unreadCount   = $this->notificationService->countUnreadNotifications($user->id);

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
            'data'         => $notifications,
        ]);
    }

    /**
     * Delete a single notification
     */
    public function delete(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request->validate(['notification_id' => 'required|integer']);
        $this->notificationService->deleteNotification($request->notification_id, $user->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification deleted.',
        ]);
    }

    /**
     * Delete all notifications
     */
    public function deleteAll()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $this->notificationService->deleteAllNotifications($user->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'All notifications deleted.',
        ]);
    }

    /**
     * Get unread count
     */
    public function getCount()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $unreadCount = $this->notificationService->countUnreadNotifications($user->id);

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
        ]);
    }
}
