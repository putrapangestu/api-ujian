<?php

namespace App\Repositories;

use App\Models\StudentGroup;
use Illuminate\Database\Eloquent\Collection;

/**
 * StudentGroup Repository Interface
 *
 * Interface ini mendefinisikan kontrak untuk StudentGroupRepository
 *
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface StudentGroupRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get active groups
     *
     * @return Collection
     */
    public function getActiveGroups(): Collection;

    /**
     * Get groups with student count
     *
     * @return Collection
     */
    public function getWithStudentCount(): Collection;

    /**
     * Search groups by name
     *
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection;

    /**
     * Get groups with available slots
     *
     * @return Collection
     */
    public function getWithAvailableSlots(): Collection;

    /**
     * Get group by ID with students
     *
     */
    public function findWithStudents(string $id): StudentGroup|null;
}
