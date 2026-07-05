<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/AuthController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    // POST /api/login
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Verrou par compte (en plus du throttle IP sur la route) — empêche
        // le bruteforce d'un compte précis depuis des IP différentes.
        $key = 'login:' . strtolower($data['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Trop de tentatives. Réessayez dans {$seconds}s.",
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'message' => 'Email ou mot de passe incorrect',
            ], 401);
        }

        RateLimiter::clear($key);

        // Supprime les anciens tokens de cet appareil
        $user->tokens()->where('name', 'tms-web')->delete();

        $token = $user->createToken('tms-web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role->value,
            ],
        ]);
    }

    // POST /api/logout
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté avec succès']);
    }
}
