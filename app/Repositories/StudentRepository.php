<?php

namespace App\Repositories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        return $this->model->where('email', $email)->first();
    }

    /**
     * Get students by group
     *
     * @param int $groupId
     * @return Collection
     */
    public function getByGroup(int $groupId): Collection
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
                      ->orWhere('email', 'like', "%{$search}%");
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
                    if (empty($studentData['nisn']) || empty($studentData['name']) || empty($studentData['email'])) {
                        $errors[] = "Baris " . ($index + 1) . ": NISN, nama, dan email wajib diisi";
                        continue;
                    }

                    // Cek duplikasi
                    if ($this->findByNisn($studentData['nisn'])) {
                        $errors[] = "Baris " . ($index + 1) . ": NISN sudah digunakan";
                        continue;
                    }

                    if ($this->findByEmail($studentData['email'])) {
                        $errors[] = "Baris " . ($index + 1) . ": Email sudah digunakan";
                        continue;
                    }

                    // Create student
                    $student = $this->create($studentData);

                    // assign role student
                    $student->assignRole('student');

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
