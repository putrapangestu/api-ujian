<?php

namespace App\Repositories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

/**
 * Student Repository Interface
 * 
 * Interface ini mendefinisikan kontrak untuk StudentRepository
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface StudentRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find student by NISN
     * 
     * @param string $nisn
     * @return Student|null
     */
    public function findByNisn(string $nisn): ?Student;

    /**
     * Find student by email
     * 
     * @param string $email
     * @return Student|null
     */
    public function findByEmail(string $email): ?Student;

    /**
     * Get students by group
     * 
     * @param int $groupId
     * @return Collection
     */
    public function getByGroup(int $groupId): Collection;

    /**
     * Get active students
     * 
     * @return Collection
     */
    public function getActiveStudents(): Collection;

    /**
     * Search students by name or NISN
     * 
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection;

    /**
     * Import students from array
     * 
     * @param array $students
     * @return array
     */
    public function importStudents(array $students): array;
}