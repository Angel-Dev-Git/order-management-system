<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // $credentials = $request->only('email', 'password');

        // if (auth()->attempt($credentials)) {
        //     $user = auth()->user();
        //     $token = $user->createToken('auth_token')->plainTextToken;

        //     return response()->json([
        //         'access_token' => $token,
        //         'token_type' => 'Bearer',
        //     ]);
        // }

        // return response()->json(['message' => 'Invalid credentials'], 401);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // $request->session()->regenerate();

        $user = $request->user()->load('role');
        $user->token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
            ],
        ]);
    }
      public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()->load('role.permissions'),
            ],
        ]);
    }

    // public function logout(Request $request)
    // {
    //     Auth::guard('web')->logout();

    //     $request->session()->invalidate();
    //     $request->session()->regenerateToken();
    //     $request->user()->currentAccessToken()->delete();
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Logout successful.',
    //     ]);
    // }

    
public function logout(Request $request)
{
    $user = $request->user();

    // Revoke the current token if bearer-token authentication is used
    if ($user && $user->currentAccessToken()) {
        $token = $user->currentAccessToken();

        if (method_exists($token, 'delete')) {
            $token->delete();
        }
    }

    // Invalidate the web session when one is active
    if (Auth::guard('web')->check()) {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Logout successful.',
    ]);
}

public function staff(Request $request)
    {
        $staff = $request->user()->where('role_id', 3)->get();
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $staff,
            ],
        ]);
    }
}
