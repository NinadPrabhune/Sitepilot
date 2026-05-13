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

    public function index(Request $request) {
        return $request->user()->deviceTokens()->latest()->get();
    }

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

    public function destroy(Request $request) {
        $data = $request->validate(['token' => 'required|string']);
        $deleted = DeviceToken::where('user_id', $request->user()->id)
                ->where('token', $data['token'])
                ->delete();

        return response()->json(['deleted' => $deleted > 0]);
    }
}
