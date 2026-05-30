<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

/**
 * @group Device Token
 * Endpoints for managing device tokens for push notifications
 */
class DeviceTokenApiController extends Controller {

    /**
     * List Device Tokens
     *
     * Returns all device tokens for the authenticated user.
     *
     * @authenticated
     *
     * @response status=200 scenario="Success"
     * [
     *   {"id": 1, "token": "fcm_token_abc123", "platform": "android", "device_name": "Pixel 7", "app_version": "1.0.0", ...}
     * ]
     */
    public function index(Request $request) {
        return $request->user()->deviceTokens()->latest()->get();
    }

    /**
     * Register Device Token
     *
     * Register or update a device token for push notifications.
     *
     * @authenticated
     *
     * @bodyParam token string required Firebase Cloud Messaging token. Example: fcm_token_abc123
     * @bodyParam platform string optional Device platform. Allowed: android, ios, web. Example: android
     * @bodyParam device_name string optional Device name/model. Example: Pixel 7
     * @bodyParam app_version string optional App version. Example: 1.0.0
     *
     * @response status=200 scenario="Token registered"
     * { "status": "ok", "device_token_id": 1 }
     * @response status=422 scenario="Validation error"
     * { "message": "Validation error", "errors": {...} }
     */
    public function store(Request $request) {
        $data = $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string|in:android,ios,web',
            'device_name' => 'nullable|string',
            'app_version' => 'nullable|string',
        ]);

        $user = $request->user();

        // Upsert: if token exists, attach to this user and update metadata
        $deviceToken = DeviceToken::updateOrCreate(
                ['token' => $data['token']],
                [
                    'user_id' => $user->id,
                    'platform' => $data['platform'] ?? null,
                    'device_name' => $data['device_name'] ?? null,
                    'app_version' => $data['app_version'] ?? null,
                    'last_seen' => now(),
                ]
        );

        return response()->json(['status' => 'ok', 'device_token_id' => $deviceToken->id]);
    }

    /**
     * Remove Device Token
     *
     * Unregister a device token for the authenticated user.
     *
     * @authenticated
     *
     * @bodyParam token string required Device token to remove. Example: fcm_token_abc123
     *
     * @response status=200 scenario="Token removed"
     * { "deleted": true }
     * @response status=200 scenario="Token not found"
     * { "deleted": false }
     */
    public function destroy(Request $request) {
        $data = $request->validate(['token' => 'required|string']);
        $deleted = DeviceToken::where('user_id', $request->user()->id)
                ->where('token', $data['token'])
                ->delete();

        return response()->json(['deleted' => $deleted > 0]);
    }
}
