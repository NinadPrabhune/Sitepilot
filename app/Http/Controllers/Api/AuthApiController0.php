<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Customer;

/**
 * @group Authentication
 * Endpoints for customer login and token-based authentication
 */
class AuthController extends Controller
{
    /**
     * Customer Login
     *
     * Authenticate a customer using email and password. Returns a Sanctum token on success.
     *
     * @bodyParam email string required Customer email address. Example: customer@example.com
     * @bodyParam password string required Customer password. Example: secret123
     *
     * @response status=200 scenario="Login successful"
     * {
     *   "status": true,
     *   "message": "Login successful",
     *   "token": "1|abc123def456...",
     *   "user": {"id": 1, "email": "customer@example.com", ...}
     * }
     * @response status=401 scenario="Invalid credentials"
     * { "status": false, "message": "Invalid email or password" }
     * @response status=422 scenario="Validation error"
     * { "status": false, "message": "Validation error", "errors": {...} }
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = Customer::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid email or password'
            ], 401);
        }

        // Optional: Create token
        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $user
        ]);
    }
}
