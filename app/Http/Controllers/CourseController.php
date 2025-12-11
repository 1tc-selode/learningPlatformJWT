<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        
        $courses = Course::select('title', 'description')->get();

        return response()->json([
            'courses' => $courses
        ]);

    }

    public function show(Course $course)
    {
        // Csak a szükséges mezők a kapcsolt usereknél, valamint a teljesítési státusz
        $students = $course->users()->select('name', 'email')->withPivot('completed_at')->get()->map(function ($user) {
            return [
                'name' => $user->name,
                'email' => $user->email,
                'completed' => !is_null($user->pivot->completed_at)
            ];
        });

        return response()->json([
            'course' => [
                'title' => $course->title,
                'description' => $course->description
            ],
            'students' => $students
        ]);
    }

    public function enroll(Course $course, Request $request)
    {
        $user = $request->user();

        if ($user->courses()->where('course_id', $course->id)->exists()) {
            return response()->json(['message' => 'Already enrolled in this course'], 409);
        }

        $user->courses()->attach($course->id, ['enrolled_at' => now()]);

        return response()->json(['message' => 'Successfully enrolled in course']);
    }

    public function complete(Course $course, Request $request)
    {
        $user = $request->user();
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $enrollment) {
            return response()->json(['message' => 'Not enrolled in this course'], 403);
        }

        if ($enrollment->completed_at) {
            return response()->json(['message' => 'Course already completed'], 409);
        }

        $enrollment->update(['completed_at' => now()]);

        return response()->json(['message' => 'Course completed']);
    }

    /**
     * Store a new course
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $course = Course::create([
            'title' => $request->title,
            'description' => $request->description
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $course,
            'message' => 'Course created successfully'
        ], 201);
    }

    /**
     * Update a course
     */
    public function update(Request $request, Course $course)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $course->update($request->only(['title', 'description']));

        return response()->json([
            'status' => 'success',
            'data' => $course->fresh(),
            'message' => 'Course updated successfully'
        ]);
    }

    /**
     * Delete a course
     */
    public function destroy(Course $course)
    {
        // Check if there are any enrollments
        if ($course->enrollments->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete course with active enrollments'
            ], 422);
        }

        $course->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Course deleted successfully'
        ]);
    }

    /**
     * Get enrolled students for a course
     */
    public function getEnrolledStudents(Course $course)
    {
        $enrolledStudents = $course->users()->select('users.id', 'users.name', 'users.email')
                                 ->withPivot('enrolled_at', 'completed_at')
                                 ->get()
                                 ->map(function ($user) {
                                     return [
                                         'id' => $user->id,
                                         'name' => $user->name,
                                         'email' => $user->email,
                                         'enrolled_at' => $user->pivot->enrolled_at,
                                         'completed_at' => $user->pivot->completed_at,
                                         'completed' => !is_null($user->pivot->completed_at)
                                     ];
                                 });

        return response()->json([
            'status' => 'success',
            'data' => [
                'course' => $course->only(['id', 'title', 'description']),
                'enrolled_students' => $enrolledStudents
            ],
            'message' => 'Enrolled students retrieved successfully'
        ]);
    }
}