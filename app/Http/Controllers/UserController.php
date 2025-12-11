<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * GET /users/me
     * A bejelentkezett felhasználó adatainak lekérése.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'stats' => [
                'enrolledCourses'  => $user->enrollments()->count(),
                'completedCourses' => $user->enrollments()->where('completed_at', true)->count(),
            ]
        ], 200);
    }

    /**
     * PUT /users/me
     * A bejelentkezett felhasználó adatainak frissítése.
     */
    public function updateMe(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'   => 'sometimes|string|max:255',
            'email'  => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|confirmed|min:8',
        ]);

        if ($request->name) {
            $user->name = $request->name;
        }
        if ($request->email) {
            $user->email = $request->email;
        }
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    /**
     * ADMIN ONLY
     * GET /users
     * Összes felhasználó listázása.
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        
        $users = User::all()->map(function ($user) {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'stats' => [
                'enrolledCourses'  => $user->enrollments()->count(),
                'completedCourses' => $user->enrollments()->whereNotNull('completed_at')->count(),
            ]
        ];
    });

    return response()->json([
        'data' => $users
    ]);

    }

    /**
     * ADMIN ONLY
     * GET /users/{id}
     * Felhasználó lekérése ID alapján.
     */
    public function show(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->trashed()) {
            return response()->json(['message' => 'User is deleted'], 404);
        }

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'stats' => [
                'enrolledCourses'  => $user->enrollments()->count(),
                'completedCourses' => $user->enrollments()->whereNotNull('completed_at')->count(),
            ]
        ]);
    }

    /**
     * ADMIN ONLY
     * DELETE /users/{id}
     * Soft delete felhasználó.
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}