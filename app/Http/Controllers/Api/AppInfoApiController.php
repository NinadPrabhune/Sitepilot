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
        /**
         * List App Info
         *
         * Returns all application information records ordered by ID descending.
         *
         * @authenticated
         *
         * @response status=200 scenario="Success"
         * {
         *   "status": true,
         *   "message": "App info retrieved successfully",
         *   "data": {
         *     "app_info": [
         *       {"id": 1, "call_us": "+1234567890", "email_us": "support@example.com", "whatsapp": "+1234567890", "version": "1.0.0", "last_updated": "2025-01-01", "privacy_policy": "https://example.com/privacy"}
         *     ]
         *   }
         * }
         */
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

        /**
         * Show App Info
         *
         * Retrieve a specific application information record by ID.
         *
         * @authenticated
         *
         * @urlParam id integer required App info record ID. Example: 1
         *
         * @response status=200 scenario="Success"
         * {
         *   "status": true,
         *   "message": "App info retrieved successfully",
         *   "data": {
         *     "app_info": {"id": 1, "call_us": "+1234567890", "email_us": "support@example.com", "version": "1.0.0"}
         *   }
         * }
         * @response status=404 scenario="Not found"
         * { "status": false, "message": "App info not found", "data": [] }
         */
        public function show($id)
        {
            try {
                $appInfo = AppInfo::findOrFail($id);

                return $this->jsonResponse(true, 'App info retrieved successfully', [
                    'app_info' => $appInfo
                ]);
            } catch (\Exception $e) {
                return $this->jsonResponse(false, $e->getMessage(), [], 500);
            }
        }

    /**
     * Create App Info
     *
     * Create a new application information record with contact details, version, and privacy policy.
     *
     * @authenticated
     *
     * @bodyParam call_us string optional Phone number for calls. Example: +1234567890
     * @bodyParam email_us string optional Support email address. Example: support@example.com
     * @bodyParam whatsapp string optional WhatsApp number. Example: +1234567890
     * @bodyParam version string optional App version. Example: 1.0.0
     * @bodyParam last_updated date optional Last updated date (YYYY-MM-DD). Example: 2025-01-01
     * @bodyParam privacy_policy string optional URL to privacy policy. Example: https://example.com/privacy
     *
     * @response status=201 scenario="Created successfully"
     * {
     *   "status": true,
     *   "message": "App info created successfully",
     *   "data": {
     *     "app_info": {"id": 1, "call_us": "+1234567890", "version": "1.0.0", ...}
     *   }
     * }
     * @response status=422 scenario="Validation error"
     * { "status": false, "message": "Validation error", "data": {"call_us": ["..."]} }
     */
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

    /**
     * Update App Info
     *
     * Update an existing application information record by ID.
     *
     * @authenticated
     *
     * @urlParam id integer required App info record ID. Example: 1
     *
     * @bodyParam call_us string optional Updated phone number for calls. Example: +1234567890
     * @bodyParam email_us string optional Updated support email. Example: support@example.com
     * @bodyParam whatsapp string optional Updated WhatsApp number. Example: +1234567890
     * @bodyParam version string optional Updated app version. Example: 1.1.0
     * @bodyParam last_updated date optional Updated last updated date (YYYY-MM-DD). Example: 2025-06-01
     * @bodyParam privacy_policy string optional Updated privacy policy URL. Example: https://example.com/privacy
     *
     * @response status=200 scenario="Updated successfully"
     * {
     *   "status": true,
     *   "message": "App info updated successfully",
     *   "data": {
     *     "app_info": {"id": 1, "call_us": "+1234567890", "version": "1.1.0", ...}
     *   }
     * }
     * @response status=404 scenario="Not found"
     * { "status": false, "message": "App info not found", "data": [] }
     * @response status=422 scenario="Validation error"
     * { "status": false, "message": "Validation error", "data": {...} }
     */
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

    /**
     * Delete App Info
     *
     * Permanently delete an application information record by ID.
     *
     * @authenticated
     *
     * @urlParam id integer required App info record ID. Example: 1
     *
     * @response status=200 scenario="Deleted successfully"
     * {
     *   "status": true,
     *   "message": "App info deleted successfully",
     *   "data": []
     * }
     * @response status=404 scenario="Not found"
     * { "status": false, "message": "App info not found", "data": [] }
     */
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
