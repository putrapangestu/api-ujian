<?php

namespace App\Repositories;

use App\Models\StudentGroup;
use Illuminate\Database\Eloquent\Collection;

/**
 * StudentGroup Repository Class
 * 
 * Repository ini menangani semua operasi database terkait StudentGroup model
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
class StudentGroupRepository extends BaseRepository implements StudentGroupRepositoryInterface
{
    /**
     * StudentGroupRepository constructor
     * 
     * @param StudentGroup $model
     */
    public function __construct(StudentGroup $model)
    {
        parent::__construct($model);
    }

    /**
     * Get active groups
     * 
     * @return Collection
     */
    public function getActiveGroups(): Collection
    {
        return $this->model->where('status', StudentGroup::STATUS_ACTIVE)->get();
    }

    /**
     * Get groups with student count
     * 
     * @return Collection
     */
    public function getWithStudentCount(): Collection
    {
        return $this->model->withCount('students')->get();
    }

    /**
     * Search groups by name
     * 
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection
    {
        return $this->model
            ->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->limit($limit)
            ->get();
    }

    /**
     * Get groups with available slots
     * 
     * @return Collection
     */
    public function getWithAvailableSlots(): Collection
    {
        return $this->model
            ->withCount('students')
            ->havingRaw('student_count < max_students')
            ->get();
    }
}