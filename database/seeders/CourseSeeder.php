<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        Course::create([
            'title' => 'Szoftverfejlesztési alapok',
            'description' => 'Alapvető programozási fogalmak és minták.',
        ]);

        Course::create([
            'title' => 'REST API fejlesztés',
            'description' => 'API-k tervezése és készítése Laravelben.',
        ]);

        Course::create([
            'title' => 'Fullstack webfejlesztés',
            'description' => 'Backend és frontend alapok.',
        ]);
    }
}