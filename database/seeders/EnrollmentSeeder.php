<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use Carbon\Carbon;

class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', 'student')->take(2)->get();
        $courses = Course::all();

        // User 1: első két kurzus
        Enrollment::create([
            'user_id' => $students[0]->id,
            'course_id' => $courses[0]->id,
            'enrolled_at' => now(),
            'completed_at' => now(),  // completed
        ]);

        Enrollment::create([
            'user_id' => $students[0]->id,
            'course_id' => $courses[1]->id,
            'enrolled_at' => now(),
            'completed_at' => null,
        ]);

        // User 2: első két kurzus
        Enrollment::create([
            'user_id' => $students[1]->id,
            'course_id' => $courses[0]->id,
            'enrolled_at' => now(),
            'completed_at' => now(), // completed
        ]);

        Enrollment::create([
            'user_id' => $students[1]->id,
            'course_id' => $courses[2]->id,
            'enrolled_at' => now(),
            'completed_at' => null,
        ]);
    }
}
