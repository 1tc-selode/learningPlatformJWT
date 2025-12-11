<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Auth\JwtAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CourseController;

Route::get('/ping', function () {return response()->json(['message' => 'API works!'], 200);});

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth' 
], function ($router) {
    Route::post('register', [JwtAuthController::class, 'register']);
    Route::post('login', [JwtAuthController::class, 'login']);
    Route::post('logout', [JwtAuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [JwtAuthController::class, 'refresh'])->middleware('auth:api');
});

// User endpoints (protected)
Route::group([
    'middleware' => ['api', 'auth:api'],
    'prefix' => 'users' 
], function ($router) {
    // Self management
    Route::get('/me', [UserController::class, 'me']); 
    Route::put('/me', [UserController::class, 'updateMe']);
    
    // User listing (limited access)
    Route::get('/', [UserController::class, 'index']); 
    Route::get('/{id}', [UserController::class, 'show']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
});

// Course endpoints (protected)
Route::group([
    'middleware' => ['api', 'auth:api'],
    'prefix' => 'courses' 
], function ($router) {
    // Course browsing and details
    Route::get('/', [CourseController::class, 'index']); 
    Route::get('/{course}', [CourseController::class, 'show']); 
    
    // Enrollment actions
    Route::post('/{course}/enroll', [CourseController::class, 'enroll']); 
    Route::post('/{course}/complete', [CourseController::class, 'complete']);
    
    // Course management (for now using CourseController - should be moved to AdminController)
    Route::post('/', [CourseController::class, 'store']); // Create course
    Route::put('/{course}', [CourseController::class, 'update']); // Update course  
    Route::delete('/{course}', [CourseController::class, 'destroy']); // Delete course
    
    // Get enrolled students
    Route::get('/{course}/students', [CourseController::class, 'getEnrolledStudents']);
});

// Enrollment management endpoints
Route::group([
    'middleware' => ['api', 'auth:api'],
    'prefix' => 'enrollments'
], function ($router) {
    // Student enrollment management
    Route::get('/', function(Request $request) {
        // Get current user's enrollments
        $user = $request->user();
        $enrollments = $user->enrollments()->with('course:id,title,description')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $enrollments,
            'message' => 'Enrollments retrieved successfully'
        ]);
    });
    
    Route::post('/', function(Request $request) {
        // Enroll in course
        $request->validate(['course_id' => 'required|exists:courses,id']);
        
        $user = $request->user();
        $courseId = $request->course_id;
        
        // Check if already enrolled
        if ($user->enrollments()->where('course_id', $courseId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Already enrolled in this course'
            ], 409);
        }
        
        $enrollment = $user->enrollments()->create([
            'course_id' => $courseId,
            'enrolled_at' => now()
        ]);
        
        $enrollment->load('course:id,title,description');
        
        return response()->json([
            'status' => 'success',
            'data' => $enrollment,
            'message' => 'Successfully enrolled in course'
        ], 201);
    });
    
    Route::delete('/{id}', function(Request $request, $id) {
        // Cancel enrollment
        $user = $request->user();
        $enrollment = $user->enrollments()->find($id);
        
        if (!$enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Enrollment not found'
            ], 404);
        }
        
        $enrollment->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Enrollment cancelled successfully'
        ]);
    });
});

// Admin endpoints (require admin role)
Route::group([
    'middleware' => ['api', 'auth:api'], // Note: admin middleware should be added when AdminController is created
    'prefix' => 'admin'
], function ($router) {
    
    // User management
    Route::group(['prefix' => 'users'], function() {
        Route::get('/', function() {
            $users = \App\Models\User::with(['enrollments.course'])->get();
            return response()->json([
                'status' => 'success',
                'data' => $users,
                'message' => 'Users retrieved successfully'
            ]);
        });
        
        Route::post('/', function(Request $request) {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'role' => 'required|in:student,admin'
            ]);
            
            $user = \App\Models\User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role
            ]);
            
            return response()->json([
                'status' => 'success',
                'data' => $user,
                'message' => 'User created successfully'
            ], 201);
        });
        
        Route::put('/{id}', function(Request $request, $id) {
            $user = \App\Models\User::find($id);
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
            }
            
            $rules = [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'role' => 'sometimes|required|in:student,admin'
            ];
            
            if ($request->has('password')) {
                $rules['password'] = 'string|min:6';
            }
            
            $request->validate($rules);
            
            $updateData = $request->only(['name', 'email', 'role']);
            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->password);
            }
            
            $user->update($updateData);
            
            return response()->json([
                'status' => 'success',
                'data' => $user->fresh(),
                'message' => 'User updated successfully'
            ]);
        });
        
        Route::delete('/{id}', function(Request $request, $id) {
            $user = \App\Models\User::find($id);
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
            }
            
            // Don't allow deleting the last admin
            if ($user->isAdmin() && \App\Models\User::where('role', 'admin')->count() <= 1) {
                return response()->json(['status' => 'error', 'message' => 'Cannot delete the last admin'], 422);
            }
            
            $user->enrollments()->delete();
            $user->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
        });
    });
    
    // Course management
    Route::group(['prefix' => 'courses'], function() {
        Route::get('/', function() {
            $courses = \App\Models\Course::withCount('enrollments')
                          ->with(['enrollments.user:id,name,email'])
                          ->get();
            return response()->json([
                'status' => 'success',
                'data' => $courses,
                'message' => 'Courses retrieved successfully'
            ]);
        });
        
        Route::delete('/{id}/force', function($id) {
            $course = \App\Models\Course::find($id);
            if (!$course) {
                return response()->json(['status' => 'error', 'message' => 'Course not found'], 404);
            }
            
            $course->enrollments()->delete();
            $course->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Course and all enrollments deleted successfully'
            ]);
        });
    });
    
    // Enrollment management
    Route::group(['prefix' => 'enrollments'], function() {
        Route::get('/', function() {
            $enrollments = \App\Models\Enrollment::with(['user:id,name,email', 'course:id,title,description'])
                                   ->orderBy('created_at', 'desc')
                                   ->get();
            return response()->json([
                'status' => 'success',
                'data' => $enrollments,
                'message' => 'Enrollments retrieved successfully'
            ]);
        });
        
        Route::post('/', function(Request $request) {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'course_id' => 'required|exists:courses,id'
            ]);
            
            // Check if already enrolled
            $exists = \App\Models\Enrollment::where('user_id', $request->user_id)
                                  ->where('course_id', $request->course_id)
                                  ->exists();
            
            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is already enrolled in this course'
                ], 422);
            }
            
            $enrollment = \App\Models\Enrollment::create([
                'user_id' => $request->user_id,
                'course_id' => $request->course_id
            ]);
            
            $enrollment->load(['user:id,name,email', 'course:id,title,description']);
            
            return response()->json([
                'status' => 'success',
                'data' => $enrollment,
                'message' => 'User enrolled successfully'
            ], 201);
        });
        
        Route::delete('/{id}', function($id) {
            $enrollment = \App\Models\Enrollment::find($id);
            if (!$enrollment) {
                return response()->json(['status' => 'error', 'message' => 'Enrollment not found'], 404);
            }
            
            $enrollment->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Enrollment removed successfully'
            ]);
        });
    });
    
    // Statistics
    Route::get('/statistics', function() {
        $stats = [
            'total_users' => \App\Models\User::count(),
            'total_admins' => \App\Models\User::where('role', 'admin')->count(),
            'total_students' => \App\Models\User::where('role', 'student')->count(),
            'total_courses' => \App\Models\Course::count(),
            'total_enrollments' => \App\Models\Enrollment::count(),
            'courses_with_enrollments' => \App\Models\Course::has('enrollments')->count(),
            'students_with_enrollments' => \App\Models\User::where('role', 'student')->has('enrollments')->count(),
            'recent_enrollments' => \App\Models\Enrollment::where('created_at', '>=', now()->subDays(7))->count(),
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $stats,
            'message' => 'Statistics retrieved successfully'
        ]);
    });
    
    Route::get('/course-stats', function() {
        $courses = \App\Models\Course::withCount('enrollments')
                      ->orderBy('enrollments_count', 'desc')
                      ->get(['id', 'title', 'created_at']);
                      
        return response()->json([
            'status' => 'success',
            'data' => $courses,
            'message' => 'Course enrollment statistics retrieved successfully'
        ]);
    });
});
