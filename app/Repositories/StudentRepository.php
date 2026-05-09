<?php

namespace App\Repositories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Student Repository Class
 *
 * Repository ini menangani semua operasi database terkait Student model
 *
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
class StudentRepository extends BaseRepository implements StudentRepositoryInterface
{
    /**
     * StudentRepository constructor
     *
     * @param Student $model
     */
    public function __construct(Student $model)
    {
        parent::__construct($model);
    }

    /**
     * Find student by NISN
     *
     * @param string $nisn
     * @return Student|null
     */
    public function findByNisn(string $nisn): ?Student
    {
        return $this->model->where('nisn', $nisn)->first();
    }

    /**
     * Find student by email
     *
     * @param string $email
     * @return Student|null
     */
    public function findByEmail(string $email): ?Student
    {
        return $this->model->whereHas('user', function ($q) use ($email) {
            $q->where('email', $email);
        })->first();
    }

    /**
     * Get students by group
     *
     * @param string $groupId
     * @return Collection
     */
    public function getByGroup(string $groupId): Collection
    {
        return $this->model->where('group_id', $groupId)->get();
    }

    /**
     * Get active students
     *
     * @return Collection
     */
    public function getActiveStudents(): Collection
    {
        return $this->model->where('status', Student::STATUS_ACTIVE)->get();
    }

    /**
     * Search students by name or NISN
     *
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection
    {
        return $this->model
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('email', 'like', "%{$search}%");
                    });
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Import students from array
     *
     * @param array $students
     * @return array
     */
    public function importStudents(array $students): array
    {
        return DB::transaction(function () use ($students) {
            $imported = [];
            $errors = [];

            foreach ($students as $index => $studentData) {
                try {
                    // Validasi data
                    if (empty($studentData['name']) || empty($studentData['email'])) {
                        $errors[] = "Baris " . ($index + 1) . ": Nama dan email wajib diisi";
                        continue;
                    }

                    // Cek duplikasi email
                    $originalEmail = $studentData['email'];
                    $emailCount = 1;
                    while (User::where('email', $studentData['email'])->exists()) {
                        $emailParts = explode('@', $originalEmail);
                        $studentData['email'] = $emailParts[0] . $emailCount . '@' . $emailParts[1];
                        $emailCount++;
                    }

                    $password = Hash::make($studentData['password_string'] ?? $studentData['birth_date']);
                    Log::info("password user : ", ['string' => $studentData['password_string'], 'password' => $password]);

                    // Create User
                    $user = User::create([
                        'email' => $studentData['email'],
                        'password' => $password,
                        'name' => $studentData['name'],
                        'role' => 'student'
                    ]);
                    $studentData['user_id'] = $user->id;

                    // Remove fields that do not exist on students table
                    unset($studentData['password_string']);
                    unset($studentData['email']);

                    // Create student
                    $student = $this->create($studentData);

                    // assign role student
                    $user->assignRole('student');

                    $imported[] = $student;

                } catch (\Exception $e) {
                    $errors[] = "Baris " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            return [
                'imported' => $imported,
                'errors' => $errors,
                'total_imported' => count($imported),
                'total_errors' => count($errors)
            ];
        });
    }
}
