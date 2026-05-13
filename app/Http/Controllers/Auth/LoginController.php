<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        
//        dd($request);
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('flutter-app')->plainTextToken;
        
//        $credentials = $request->only('email', 'password');
//
//        $token = JWTAuth::attempt($credentials);

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }
}
