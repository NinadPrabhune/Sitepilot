<?php

namespace App\Http\Controllers;

use App\Models\ChNotificationUser;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPageController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display user notifications page.
     *
     * @authenticated
     * @response view="notifications.index"
     */
    public function index(Request $request)
    {
        $notifications = ChNotificationUser::with('notification')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Mark a single notification as read.
     *
     * @authenticated
     * @urlParam notificationUser int required Notification user ID.
     * @response {
     *   "success": true,
     *   "unread_count": 5
     * }
     */
    public function markAsRead(ChNotificationUser $notificationUser, Request $request)
    {
        abort_unless($notificationUser->user_id === $request->user()->id, 403);

        $notificationUser->update(['read_at' => $notificationUser->read_at ?? now()]);
        
        $unreadCount = $this->notificationService->countUnreadNotifications($request->user()->id);

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * @authenticated
     * @response {
     *   "success": true,
     *   "unread_count": 0
     * }
     */
    public function markAllAsRead(Request $request)
    {
        $this->notificationService->markAllAsRead($request->user()->id);

        return response()->json([
            'success' => true,
            'unread_count' => 0
        ]);
    }

    /**
     * Get unread notifications (AJAX).
     *
     * @authenticated
     * @queryParam limit integer Number of notifications to fetch. Default: 10.
     * @response {
     *   "success": true,
     *   "notifications": [],
     *   "unread_count": 5
     * }
     */
    public function unread(Request $request)
    {
        $limit = $request->get('limit', 10);

        // Ensure this returns a paginator, not a plain collection
        $notifications = $this->notificationService->getUnreadNotifications(
            $request->user()->id,
            $limit
        );

        $unreadCount = $this->notificationService->countUnreadNotifications($request->user()->id);

        return response()->json([
            'success' => true,
            'notifications' => $notifications->items(),
            'unread_count' => $unreadCount
        ]);
    }


    /**
     * Delete a single notification.
     *
     * @authenticated
     * @urlParam notificationUser int required Notification user ID.
     * @response {
     *   "success": true,
     *   "unread_count": 5
     * }
     */
    public function delete(ChNotificationUser $notificationUser, Request $request)
    {
        abort_unless($notificationUser->user_id === $request->user()->id, 403);
        $notificationUser->delete();
        
        $unreadCount = $this->notificationService->countUnreadNotifications($request->user()->id);

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Delete all notifications for the authenticated user.
     *
     * @authenticated
     * @response {
     *   "success": true,
     *   "unread_count": 0
     * }
     */
    public function deleteAll(Request $request)
    {
        $this->notificationService->deleteAllNotifications($request->user()->id);

        return response()->json([
            'success' => true,
            'unread_count' => 0
        ]);
    }

    /**
     * Get unread notification count (AJAX).
     *
     * @authenticated
     * @response {
     *   "success": true,
     *   "unread_count": 5
     * }
     */
    public function getCount(Request $request)
    {
        $unreadCount = $this->notificationService->countUnreadNotifications($request->user()->id);

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount
        ]);
    }
}


