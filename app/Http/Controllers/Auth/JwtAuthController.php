<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // <-- Szükséges a jelszó hasheléséhez
use Illuminate\Support\Facades\Validator; // <-- Szükséges a validációhoz
use App\Models\User; // <-- Szükséges az új User létrehozásához
use Tymon\JWTAuth\Facades\JWTAuth; // <-- Fontos import


class JwtAuthController extends Controller
{
    /**
     * Új felhasználó regisztrálása és azonnali bejelentkezése.
     */
    public function register(Request $request)
    {
        // 1. Validáció
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // 2. Felhasználó létrehozása
        $user = User::create(array_merge(
            $validator->validated(),
            ['password' => Hash::make($request->password)] // Jelszó hashelése
        ));
        
        // 3. Token generálása (azonnali bejelentkezés)
        $token = Auth::guard('api')->attempt($request->only('email', 'password'));

        return $this->respondWithToken($token);
    }

    /**
     * Bejelentkezés és token generálás.
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Itt történik a hitelesítés és a token generálás, ha sikeres
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * A felhasználó adatainak lekérése (példa védett útvonal).
     */
    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    /**
     * Token érvénytelenítése (Kijelentkezés).
     */
    public function logout()
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Token frissítése.
     */
    public function refresh()
{
    // Új token generálása a régi, de még érvényes token alapján
    
    /** @var JWTGuard $guard */ 
    $guard = Auth::guard('api'); // Vagy használd közvetlenül a hívásban

    // Ha mégis jelzi a hibát, a kód futni fog, hagyd figyelmen kívül, vagy használd az alábbi 
    // megkerülő megoldást.
    
    return $this->respondWithToken($guard->refresh());
}

    /**
     * Segédfüggvény a token válasz formázására.
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }
}
