<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class StudentUserSeeder extends Seeder
{
    public function run(): void
    {
        $studentRole = Role::firstOrCreate(['name' => 'student']);

        $count = (int) env('SEED_STUDENTS_COUNT', 50);
        if ($count < 1) {
            return;
        }

        $group = StudentGroup::query()
            ->where('status', StudentGroup::STATUS_ACTIVE)
            ->first();

        if (! $group) {
            $group = StudentGroup::create([
                'name' => 'Default Group',
                'description' => 'Auto-created by StudentUserSeeder',
                'max_students' => max(50, $count),
                'status' => StudentGroup::STATUS_ACTIVE,
            ]);
        }

        $timestamp = Carbon::now()->format('YmdHis');
        $hasEmail = Schema::hasColumn('students', 'email');
        $hasNisn = Schema::hasColumn('students', 'nisn');
        $hasPhone = Schema::hasColumn('students', 'phone');
        $hasAddress = Schema::hasColumn('students', 'address');
        $hasBirthDate = Schema::hasColumn('students', 'birth_date');
        $hasStatus = Schema::hasColumn('students', 'status');

        for ($i = 1; $i <= $count; $i++) {
            $email = "student{$timestamp}{$i}@example.com";

            $user = User::create([
                'name' => "Student {$i}",
                'email' => $email,
                'password' => 'password123',
            ]);

            $user->assignRole($studentRole);

            $studentData = [
                'name' => $user->name,
                'group_id' => $group->id,
                'user_id' => $user->id,
            ];

            if ($hasEmail) {
                $studentData['email'] = $user->email;
            }

            if ($hasNisn) {
                $studentData['nisn'] = (string) (1000000000 + $i);
            }

            if ($hasPhone) {
                $studentData['phone'] = null;
            }

            if ($hasAddress) {
                $studentData['address'] = null;
            }

            if ($hasBirthDate) {
                $studentData['birth_date'] = Carbon::now()->subYears(18)->toDateString();
            }

            if ($hasStatus) {
                $studentData['status'] = Student::STATUS_ACTIVE;
            }

            Student::create($studentData);
        }
    }
}
