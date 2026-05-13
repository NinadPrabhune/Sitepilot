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

    public function index(Request $request)
    {
        $notifications = ChNotificationUser::with('notification')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('notifications.index', compact('notifications'));
    }

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

    public function markAllAsRead(Request $request)
    {
        $this->notificationService->markAllAsRead($request->user()->id);

        return response()->json([
            'success' => true,
            'unread_count' => 0
        ]);
    }

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

    public function deleteAll(Request $request)
    {
        $this->notificationService->deleteAllNotifications($request->user()->id);

        return response()->json([
            'success' => true,
            'unread_count' => 0
        ]);
    }

    public function getCount(Request $request)
    {
        $unreadCount = $this->notificationService->countUnreadNotifications($request->user()->id);

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount
        ]);
    }
}


