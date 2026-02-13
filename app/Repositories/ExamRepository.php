<?php

namespace App\Repositories;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Collection;

/**
 * Exam Repository Class
 * 
 * Repository ini menangani semua operasi database terkait Exam model
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
class ExamRepository extends BaseRepository implements ExamRepositoryInterface
{
    /**
     * ExamRepository constructor
     * 
     * @param Exam $model
     */
    public function __construct(Exam $model)
    {
        parent::__construct($model);
    }

    /**
     * Get active exams
     * 
     * @return Collection
     */
    public function getActiveExams(): Collection
    {
        return $this->model
            ->whereHas('questions')
            ->get();
    }

    /**
     * Search exams by title or description
     * 
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection
    {
        return $this->model
            ->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Get exams with question count
     * 
     * @return Collection
     */
    public function getWithQuestionCount(): Collection
    {
        return $this->model
            ->withCount('questions')
            ->get();
    }

    /**
     * Get exams by passing score range
     * 
     * @param int $minScore
     * @param int $maxScore
     * @return Collection
     */
    public function getByPassingScoreRange(int $minScore, int $maxScore): Collection
    {
        return $this->model
            ->whereBetween('passing_score', [$minScore, $maxScore])
            ->get();
    }
}