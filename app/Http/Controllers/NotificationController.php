<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get the user's unread notifications.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnreadNotifications(Request $request)
    {
        $user = Auth::user();

        // Get all unread notifications
        $notifications = $user->unreadNotifications;

        // Log the notification types for debugging
        $notificationTypes = $notifications->pluck('type')->toArray();
        Log::info('Fetched unread notifications', [
            'user_id' => $user->id,
            'count' => $notifications->count(),
            'types' => $notificationTypes
        ]);

        // Check if we have any batch complete notifications
        $batchCompleteNotifications = $notifications->where('type', 'App\\Notifications\\InvoiceBatchComplete')->count();
        if ($batchCompleteNotifications > 0) {
            Log::info('Found batch complete notifications', [
                'user_id' => $user->id,
                'count' => $batchCompleteNotifications
            ]);
        }

        return response()->json([
            'notifications' => $notifications
        ]);
    }

    /**
     * Mark a notification as read.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();

            return response()->json([
                'success' => true
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Notification not found'
        ], 404);
    }

    /**
     * Mark all notifications as read.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true
        ]);
    }
}
