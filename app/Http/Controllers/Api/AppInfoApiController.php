<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @group App Info
 * Endpoints for application information including version, contact details, and privacy policy
 */
class AppInfoApiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $appInfo = AppInfo::orderBy('id', 'desc')->get();

            return $this->jsonResponse(true, 'App info retrieved successfully', [
                'app_info' => $appInfo
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'call_us' => 'nullable|string',
                'email_us' => 'nullable|string',
                'whatsapp' => 'nullable|string',
                'version' => 'nullable|string',
                'last_updated' => 'nullable|date',
                'privacy_policy' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->jsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            $appInfo = AppInfo::create([
                'call_us' => $request->call_us,
                'email_us' => $request->email_us,
                'whatsapp' => $request->whatsapp,
                'version' => $request->version,
                'last_updated' => $request->last_updated,
                'privacy_policy' => $request->privacy_policy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->jsonResponse(true, 'App info created successfully', [
                'app_info' => $appInfo
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'call_us' => 'nullable|string',
                'email_us' => 'nullable|string',
                'whatsapp' => 'nullable|string',
                'version' => 'nullable|string',
                'last_updated' => 'nullable|date',
                'privacy_policy' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->jsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            $appInfo = AppInfo::find($id);

            if (!$appInfo) {
                return $this->jsonResponse(false, 'App info not found', [], 404);
            }

            $appInfo->update([
                'call_us' => $request->call_us,
                'email_us' => $request->email_us,
                'whatsapp' => $request->whatsapp,
                'version' => $request->version,
                'last_updated' => $request->last_updated,
                'privacy_policy' => $request->privacy_policy,
                'updated_at' => now(),
            ]);

            return $this->jsonResponse(true, 'App info updated successfully', [
                'app_info' => $appInfo
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $appInfo = AppInfo::find($id);

            if (!$appInfo) {
                return $this->jsonResponse(false, 'App info not found', [], 404);
            }

            $appInfo->delete();

            return $this->jsonResponse(true, 'App info deleted successfully', []);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    private function jsonResponse($status, $message, $data = [], $code = 200)
    {
        if (is_string($status)) {
            $message = $status;
            $status = true;
            $code = 200;
        }

        $response = [
            'status' => $status,
            'message' => $message,
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }
}
