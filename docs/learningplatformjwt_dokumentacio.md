# Learning Platform JWT REST API – Teljes Dokumentáció és Kód

Ez a dokumentáció a Laravel alapú Learning Platform (Tanulási Platform) backend API teljes fejlesztési útmutatója. A projekt célja egy olyan REST API létrehozása, amely lehetővé teszi a diákok számára kurzusokra való jelentkezést, tanulmányaik követését, és egy teljes körű admin felületet biztosít a kurzusok és felhasználók kezelésére.

---

## Projekt Áttekintés

**Base URL-ek:**
- XAMPP: `http://localhost/learningPlatformJWT/public/api`
- Laravel serve: `http://127.0.0.1:8000/api`

**Technológiák:**
- Laravel 12
- Tymon JWT Auth (JWT token hitelesítés)
- MySQL adatbázis
- PHPUnit tesztelés

**Adatbázis neve:** `learning_platform`

---

## Projekt Struktúra

```
learningPlatformJWT/
├── app/
│   ├── Http/
│   │   ├── Controllers/         # API vezérlők
│   │   │   ├── Controller.php   # Alap vezérlő
│   │   │   ├── Auth/            # Autentikációs vezérlők
│   │   │   │   └── JwtAuthController.php    # JWT autentikáció
│   │   │   ├── CourseController.php         # Kurzusok kezelése
│   │   │   └── UserController.php           # Felhasználó kezelés
│   │   └── Middleware/          # Middleware-ek
│   ├── Models/                  # Adatmodellek
│   │   ├── User.php             # Felhasználó modell
│   │   ├── Course.php           # Kurzus modell
│   │   └── Enrollment.php       # Beiratkozás modell
│   └── Providers/               # Laravel szolgáltatók
├── database/
│   ├── factories/               # Tesztadat generátorok
│   │   ├── UserFactory.php
│   │   ├── CourseFactory.php
│   │   └── EnrollmentFactory.php
│   ├── migrations/              # Adatbázis szerkezet
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2025_12_11_072123_create_personal_access_tokens_table.php
│   │   ├── 2025_12_11_074308_create_courses_table.php
│   │   └── 2025_12_11_074457_create_enrollments_table.php
│   └── seeders/                 # Tesztadat feltöltés
│       ├── DatabaseSeeder.php
│       ├── UserSeeder.php
│       ├── CourseSeeder.php
│       └── EnrollmentSeeder.php
├── routes/
│   └── api.php                  # API útvonalak
├── tests/
│   └── Feature/                 # API funkció tesztek
│       ├── AuthApiTest.php
│       ├── CourseApiTest.php
│       ├── UserApiTest.php
│       └── EnrollmentApiTest.php
├── docs/                        # Dokumentáció
│   ├── postman_collection.json
│   └── learningplatformjwt_dokumentacio.md
├── config/
│   └── jwt.php                  # JWT konfiguráció
├── composer.json                # Composer konfiguráció
├── package.json                 # NPM konfiguráció
├── phpunit.xml                  # PHPUnit teszt konfiguráció
└── vite.config.js               # Vite frontend build
```

---

## Adatbázis Terv

```
+---------------------+     +---------------------+       +-----------------+        +------------+
|personal_access_tokens|    |        users        |       |  enrollments    |        |  courses   |
+---------------------+     +---------------------+       +-----------------+        +------------+
| id (PK)             |   _1| id (PK)             |1__    | id (PK)         |     __1| id (PK)    |
| tokenable_id (FK)   |K_/  | name                |   \__N| user_id (FK)    |    /   | title      |
| tokenable_type      |     | email (unique)      |       | course_id (FK)  |M__/    | description|
| name                |     | password            |       | enrolled_at     |        | created_at |
| token (unique)      |     | role (enum)         |       | completed_at    |        | updated_at |
| abilities           |     | email_verified_at   |       +-----------------+        +------------+
| last_used_at        |     | remember_token      |
| created_at          |     | created_at          |
| updated_at          |     | updated_at          |
+---------------------+     +---------------------+
```

### Kapcsolatok:
- **User -> Enrollment:** 1:N (egy felhasználónak több beiratkozása lehet)
- **Course -> Enrollment:** 1:N (egy kurzusra több diák jelentkezhet)
- **User -> Course:** N:M (many-to-many kapcsolat az enrollments táblán keresztül)
- **Role enum:** 'student', 'admin' (diák vagy adminisztrátor)

---

## I. Modul - Telepítés és Konfiguráció

### 1. Laravel Projekt Létrehozása

```powershell
# Projekt létrehozása
composer create-project laravel/laravel --prefer-dist learningPlatformJWT

# Könyvtár váltás
cd learningPlatformJWT
```

### 2. .env Fájl Konfiguráció

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=learning_platform
DB_USERNAME=root
DB_PASSWORD=
```

### 3. JWT Auth Telepítése

```powershell
# Tymon JWT Auth telepítése
composer require tymon/jwt-auth

# Konfiguráció publikálása
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"

# JWT secret generálása
php artisan jwt:secret
```

### 4. JWT Konfiguráció (config/jwt.php)

```php
<?php

return [
    'secret' => env('JWT_SECRET'),
    'keys' => [
        'public' => env('JWT_PUBLIC_KEY'),
        'private' => env('JWT_PRIVATE_KEY'),
        'passphrase' => env('JWT_PASSPHRASE'),
    ],
    'ttl' => env('JWT_TTL', 60), // 60 minutes
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160), // 2 weeks
    'algo' => env('JWT_ALGO', 'HS256'),
    'required_claims' => [
        'iss',
        'iat',
        'exp',
        'nbf',
        'sub',
        'jti',
    ],
    'persistent_claims' => [],
    'lock_subject' => true,
    'leeway' => env('JWT_LEEWAY', 0),
    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),
    'decrypt_cookies' => false,
    'providers' => [
        'jwt' => Tymon\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth' => Tymon\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => Tymon\JWTAuth\Providers\Storage\Illuminate::class,
    ],
];
```

### 5. Auth Guard Konfiguráció (config/auth.php)

```php
'defaults' => [
    'guard' => 'api',
    'passwords' => 'users',
],

'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

### 6. Első Teszt

```powershell
# Laravel szerver indítása
php artisan serve

# POSTMAN teszt
# GET http://127.0.0.1:8000/api/ping
```

---

## II. Modul - Adatbázis és Modellek

### 1. Modellek és Migrációk Létrehozása

```powershell
# Course modell és migráció létrehozása
php artisan make:model Course -m

# Enrollment modell és migráció létrehozása
php artisan make:model Enrollment -m
```

**Megjegyzés:** A User modell már létezik a Laravel alaptelepítésében.

### 2. Migrációk Konfigurálása

#### users tábla (0001_01_01_000000_create_users_table.php)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migráció futtatása - users tábla létrehozása
     */
    public function up(): void
    {
        // Users tábla létrehozása - felhasználók tárolása
        Schema::create('users', function (Blueprint $table) {
            $table->id();                                      // Primary key (auto increment)
            $table->string('name');                            // Felhasználó neve
            $table->string('email')->unique();                // Email cím (egyedi)
            $table->timestamp('email_verified_at')->nullable(); // Email megerősítés időpontja
            $table->string('password');                        // Titkosított jelszó
            $table->enum('role', ['student', 'admin']);        // Szerepkör (diák vagy admin)
            $table->softDeletes();                             // deleted_at mező (soft delete támogatás)
            $table->rememberToken();                           // "Remember me" token
            $table->timestamps();                              // created_at és updated_at mezők
        });

        // Jelszó visszaállítási tokenek táblája
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();               // Email cím (primary key)
            $table->string('token');                          // Visszaállítási token
            $table->timestamp('created_at')->nullable();      // Token létrehozás időpontja
        });

        // Session-ök tárolása (bejelentkezett felhasználók munkamenetei)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();                  // Session ID (primary key)
            $table->foreignId('user_id')->nullable()->index(); // Felhasználó ID (nullable, indexelt)
            $table->string('ip_address', 45)->nullable();     // IP cím (IPv4/IPv6)
            $table->text('user_agent')->nullable();           // Böngésző információ
            $table->longText('payload');                      // Session adatok
            $table->integer('last_activity')->index();        // Utolsó aktivitás időbélyeg (indexelt)
        });
    }

    /**
     * Migráció visszavonása - táblák törlése
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
```

#### courses tábla (2025_12_11_074308_create_courses_table.php)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migráció futtatása - courses tábla létrehozása
     */
    public function up(): void
    {
        // Courses tábla létrehozása - kurzusok tárolása
        Schema::create('courses', function (Blueprint $table) {
            $table->id();                          // Primary key (auto increment)
            $table->string('title');               // Kurzus címe
            $table->text('description');           // Kurzus leírása
            $table->timestamps();                  // created_at és updated_at mezők
        });
    }

    /**
     * Migráció visszavonása - courses tábla törlése
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
```

#### enrollments tábla (2025_12_11_074457_create_enrollments_table.php)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migráció futtatása - enrollments tábla létrehozása
     */
    public function up(): void
    {
        // Enrollments tábla létrehozása - beiratkozások tárolása
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();                                                    // Primary key (auto increment)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Foreign key a users táblára, cascade delete
            $table->foreignId('course_id')->constrained()->cascadeOnDelete(); // Foreign key a courses táblára, cascade delete
            $table->timestamp('enrolled_at')->nullable();                   // Beiratkozás időpontja
            $table->timestamp('completed_at')->nullable();                  // Befejezés időpontja
            $table->timestamps();                                            // created_at és updated_at mezők
            
            // Unique constraint: egy user csak egyszer jelentkezhet egy kurzusra
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Migráció visszavonása - enrollments tábla törlése
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
```

### 3. Modell Fájlok Konfigurálása

#### app/Models/User.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * User modell - Felhasználók kezelése
 * JWT Subject implementáció a token hitelesítéshez
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * Tömegesen kitölthető mezők
     */
    protected $fillable = [
        'name',        // Felhasználó neve
        'email',       // Email cím
        'password',    // Jelszó (titkosítva lesz tárolva)
        'role',        // Szerepkör (student/admin)
    ];

    /**
     * Rejtett mezők - nem jelennek meg JSON-ben
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Egy-sok kapcsolat: User -> Enrollment
     * Egy felhasználónak több beiratkozása lehet
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Sok-sok kapcsolat: User <-> Course (enrollments táblán keresztül)
     * A felhasználó kurzusai
     */
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'enrollments');
    }

    /**
     * Ellenőrzi, hogy a felhasználó admin-e
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * JWT token subject identifier
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT token custom claims
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
```

#### app/Models/Course.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Course modell - Kurzusok kezelése
 */
class Course extends Model
{
    use HasFactory;

    /**
     * Tömegesen kitölthető mezők
     */
    protected $fillable = [
        'title',       // Kurzus címe
        'description', // Kurzus leírása
    ];

    /**
     * Egy-sok kapcsolat: Course -> Enrollment
     * Egy kurzusnak több beiratkozása lehet
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Sok-sok kapcsolat: Course <-> User (enrollments táblán keresztül)
     * A kurzusra jelentkezett felhasználók
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'enrollments');
    }
}
```

#### app/Models/Enrollment.php

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Enrollment modell - Beiratkozások kezelése
 * Ez a pivot modell kezeli a User és Course közötti Many-to-Many kapcsolatot
 */
class Enrollment extends Model
{
    use HasFactory;

    /**
     * Timestamps kikapcsolása (custom timestamp mezők használata)
     */
    public $timestamps = false;

    /**
     * Tömegesen kitölthető mezők
     */
    protected $fillable = [
        'user_id',      // Felhasználó azonosító (foreign key)
        'course_id',    // Kurzus azonosító (foreign key)
        'enrolled_at',  // Beiratkozás időpontja
        'completed_at', // Befejezés időpontja
    ];

    /**
     * Dátum mezők casting-je
     */
    protected $dates = [
        'enrolled_at',
        'completed_at',
    ];

    /**
     * Fordított egy-sok kapcsolat: Enrollment -> User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Fordított egy-sok kapcsolat: Enrollment -> Course
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
```

### 4. Migráció Futtatása

```powershell
php artisan migrate
```

---

## III. Modul - Seeding (Tesztadatok)

### 1. Seederek Létrehozása

```powershell
php artisan make:seeder UserSeeder
php artisan make:seeder CourseSeeder
php artisan make:seeder EnrollmentSeeder
```

### 2. Seederek Konfigurálása

#### database/seeders/UserSeeder.php

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * UserSeeder - Felhasználók adatbázisba töltése
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin felhasználó létrehozása
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        // Teszt diák létrehozása
        User::firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Test Student',
                'password' => Hash::make('student123'),
                'role' => 'student',
            ]
        );

        // További random diákok generálása
        User::factory(8)->create();
    }
}
```

#### database/seeders/CourseSeeder.php

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;

/**
 * CourseSeeder - Kurzusok adatbázisba töltése
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            [
                'title' => 'Laravel Alapok',
                'description' => 'Tanuld meg a Laravel framework alapjait és építs fel modern webalkalmazásokat.',
            ],
            [
                'title' => 'Vue.js Frontend Fejlesztés',
                'description' => 'Modern frontend fejlesztés Vue.js segítségével.',
            ],
            [
                'title' => 'API Fejlesztés REST és GraphQL',
                'description' => 'Professzionális API-k építése REST és GraphQL technológiákkal.',
            ],
        ];

        foreach ($courses as $course) {
            Course::create($course);
        }
    }
}
```

#### database/seeders/EnrollmentSeeder.php

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;

/**
 * EnrollmentSeeder - Beiratkozások létrehozása
 */
class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'student')->get();
        $courses = Course::all();

        foreach ($users as $user) {
            // Véletlenszerű számú kurzusra való jelentkezés (1-2 kurzus)
            $randomCourses = $courses->random(rand(1, 2));
            
            foreach ($randomCourses as $course) {
                Enrollment::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'enrolled_at' => now(),
                ]);
            }
        }
    }
}
```

#### database/seeders/DatabaseSeeder.php

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder - Fő seeder osztály
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seederek meghívása sorrendben
        $this->call([
            UserSeeder::class,       // 1. Először a felhasználók
            CourseSeeder::class,     // 2. Majd a kurzusok
            EnrollmentSeeder::class, // 3. Végül a beiratkozások
        ]);
    }
}
```

### 3. Seeding Futtatása

```powershell
php artisan db:seed
```

---

## IV. Modul - Controller-ek és API Végpontok

### 1. Controller-ek Létrehozása

```powershell
php artisan make:controller Auth/JwtAuthController
php artisan make:controller CourseController --api
php artisan make:controller UserController --api
```

### 2. JwtAuthController Implementálása

#### app/Http/Controllers/Auth/JwtAuthController.php

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * JwtAuthController - JWT alapú autentikáció kezelése
 */
class JwtAuthController extends Controller
{
    /**
     * Felhasználó regisztrálása
     * POST /api/auth/register
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'sometimes|in:student,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->input('role', 'student'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * Bejelentkezés és JWT token generálása
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = auth('api')->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

    /**
     * Kijelentkezés és token invalidálása
     * POST /api/auth/logout
     */
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Token frissítése
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        $newToken = JWTAuth::refresh(JWTAuth::getToken());

        return response()->json([
            'status' => 'success',
            'message' => 'Token refreshed',
            'access_token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}
```

### 3. CourseController Implementálása

#### app/Http/Controllers/CourseController.php

```php
<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CourseController - Kurzusok kezelése
 */
class CourseController extends Controller
{
    /**
     * Összes kurzus listázása
     * GET /api/courses
     */
    public function index()
    {
        $courses = Course::all();
        
        return response()->json([
            'status' => 'success',
            'data' => $courses,
            'message' => 'Courses retrieved successfully'
        ]);
    }

    /**
     * Új kurzus létrehozása (csak admin)
     * POST /api/courses
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $course = Course::create($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $course,
            'message' => 'Course created successfully'
        ], 201);
    }

    /**
     * Adott kurzus megtekintése
     * GET /api/courses/{course}
     */
    public function show(Course $course)
    {
        return response()->json([
            'status' => 'success',
            'data' => $course,
            'message' => 'Course retrieved successfully'
        ]);
    }

    /**
     * Kurzus módosítása (csak admin)
     * PUT /api/courses/{course}
     */
    public function update(Request $request, Course $course)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $course->update($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $course,
            'message' => 'Course updated successfully'
        ]);
    }

    /**
     * Kurzus törlése (csak admin)
     * DELETE /api/courses/{course}
     */
    public function destroy(Course $course)
    {
        $course->enrollments()->delete();
        $course->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Course deleted successfully'
        ]);
    }

    /**
     * Beiratkozás kurzusra
     * POST /api/courses/{course}/enroll
     */
    public function enroll(Request $request, Course $course)
    {
        $user = $request->user();

        // Ellenőrizzük, hogy már beiratkozott-e
        if ($user->enrollments()->where('course_id', $course->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Already enrolled in this course'
            ], 409);
        }

        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $enrollment,
            'message' => 'Successfully enrolled in course'
        ], 201);
    }

    /**
     * Kurzus teljesítése
     * POST /api/courses/{course}/complete
     */
    public function complete(Request $request, Course $course)
    {
        $user = $request->user();

        $enrollment = $user->enrollments()->where('course_id', $course->id)->first();

        if (!$enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not enrolled in this course'
            ], 404);
        }

        if ($enrollment->completed_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Course already completed'
            ], 409);
        }

        $enrollment->update(['completed_at' => now()]);

        return response()->json([
            'status' => 'success',
            'data' => $enrollment,
            'message' => 'Course completed successfully'
        ]);
    }

    /**
     * Beiratkozott diákok lekérése (admin)
     * GET /api/courses/{course}/students
     */
    public function getEnrolledStudents(Course $course)
    {
        $enrollments = $course->enrollments()->with('user:id,name,email')->get();

        return response()->json([
            'status' => 'success',
            'data' => $enrollments,
            'message' => 'Enrolled students retrieved successfully'
        ]);
    }
}
```

### 4. UserController Implementálása

#### app/Http/Controllers/UserController.php

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * UserController - Felhasználók kezelése
 */
class UserController extends Controller
{
    /**
     * Összes felhasználó listázása (admin)
     * GET /api/users
     */
    public function index()
    {
        $users = User::all();
        
        return response()->json([
            'status' => 'success',
            'data' => $users,
            'message' => 'Users retrieved successfully'
        ]);
    }

    /**
     * Saját profil lekérése
     * GET /api/users/me
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user(),
            'message' => 'User profile retrieved successfully'
        ]);
    }

    /**
     * Saját profil frissítése
     * PUT /api/users/me
     */
    public function updateMe(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['name', 'email']);
        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'status' => 'success',
            'data' => $user->fresh(),
            'message' => 'Profile updated successfully'
        ]);
    }

    /**
     * Adott felhasználó megtekintése
     * GET /api/users/{id}
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $user,
            'message' => 'User retrieved successfully'
        ]);
    }

    /**
     * Felhasználó törlése (admin)
     * DELETE /api/users/{id}
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $user->enrollments()->delete();
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }
}
```

---

## V. API Végpontok Részletes Dokumentációja

### Általános Információk

**Content-Type:** `application/json`
**Accept:** `application/json`

**Hitelesítés:**
```
Authorization: Bearer {jwt_token}
```

**Hibakódok:**
- `400 Bad Request` - Hibás formátumú kérés
- `401 Unauthorized` - Érvénytelen token vagy hitelesítés szükséges
- `403 Forbidden` - Nincs jogosultság (admin jogok szükségesek)
- `404 Not Found` - Erőforrás nem található
- `409 Conflict` - Ütköző művelet (pl. már beiratkozva)
- `422 Unprocessable Entity` - Validációs hiba

### Publikus végpontok (token nélkül)

#### POST /api/auth/register
Új felhasználó regisztrálása.

**Kérés törzse:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "role": "student"
}
```

**Válasz:** 201 Created
```json
{
  "status": "success",
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "student",
    "created_at": "2025-12-11T10:30:00.000000Z",
    "updated_at": "2025-12-11T10:30:00.000000Z"
  }
}
```

#### POST /api/auth/login
Bejelentkezés JWT token generálásával.

**Kérés törzse:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Válasz:** 200 OK
```json
{
  "status": "success",
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "student"
  },
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

### Védett végpontok (autentikáció szükséges)

#### POST /api/auth/logout
Kijelentkezés és token invalidálása.

**Válasz:** 200 OK
```json
{
  "status": "success",
  "message": "Successfully logged out"
}
```

#### POST /api/auth/refresh
JWT token frissítése.

**Válasz:** 200 OK
```json
{
  "status": "success",
  "message": "Token refreshed",
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

### Kurzus végpontok

#### GET /api/courses
Összes kurzus listázása.

**Válasz:** 200 OK
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "title": "Laravel Alapok",
      "description": "Tanuld meg a Laravel framework alapjait...",
      "created_at": "2025-12-11T10:00:00.000000Z",
      "updated_at": "2025-12-11T10:00:00.000000Z"
    }
  ],
  "message": "Courses retrieved successfully"
}
```

#### POST /api/courses
Új kurzus létrehozása (csak admin).

**Kérés törzse:**
```json
{
  "title": "Új Kurzus",
  "description": "Kurzus leírása..."
}
```

#### POST /api/courses/{id}/enroll
Beiratkozás kurzusra.

**Válasz:** 201 Created
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "user_id": 1,
    "course_id": 1,
    "enrolled_at": "2025-12-11T10:30:00.000000Z",
    "completed_at": null
  },
  "message": "Successfully enrolled in course"
}
```

#### POST /api/courses/{id}/complete
Kurzus teljesítése.

### Felhasználó végpontok

#### GET /api/users/me
Saját profil lekérése.

#### PUT /api/users/me
Saját profil frissítése.

#### GET /api/users (csak admin)
Összes felhasználó listázása.

#### DELETE /api/users/{id} (csak admin)
Felhasználó törlése.

### Admin végpontok

#### GET /api/admin/users
Felhasználók kezelése statisztikákkal.

#### GET /api/admin/courses
Kurzusok kezelése beiratkozási adatokkal.

#### GET /api/admin/enrollments
Összes beiratkozás listázása.

#### GET /api/admin/statistics
Rendszer statisztikák lekérése.

---

## VI. Tesztelés és Deployment

### 1. PHPUnit Tesztek

```powershell
# Tesztek futtatása
php artisan test

# Specific teszt futtatása
php artisan test --filter=AuthTest
```

### 2. API Tesztelés Postman-nel

A dokumentációval együtt található Postman collection tartalmazza az összes API végpontot tesztelési példákkal.

### 3. Production Deployment

```powershell
# Optimalizáció
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Adatbázis migráció production-ben
php artisan migrate --force
```

---

## Függelék

### A. Gyakori Hibák és Megoldások

1. **JWT Secret hiánya**
   - Futtatás: `php artisan jwt:secret`

2. **CORS problémák**
   - Laravel CORS csomag telepítése és konfigurálása

3. **Database connection errors**
   - .env fájl ellenőrzése
   - MySQL szerver státusz

### B. Hasznos Artisan Parancsok

```powershell
# Route lista
php artisan route:list

# Middleware lista
php artisan route:list --middleware

# Model factory futtatása
php artisan tinker
User::factory(10)->create()
```

### C. Biztonsági Megfontolások

1. **JWT Token biztonság**
   - Rövid TTL beállítása (60 perc)
   - Refresh token mechanizmus használata
   - Token blacklist engedélyezése

2. **Input validáció**
   - Minden bemeneti adat validálása
   - SQL injection védelem (Eloquent ORM)
   - XSS védelem

3. **Role-based Access Control**
   - Admin middleware használata
   - Endpoint szintű jogosultság ellenőrzés

---

**Készítette:** Learning Platform JWT Development Team  
**Verzió:** 1.0  
**Utolsó frissítés:** 2025. december 11.