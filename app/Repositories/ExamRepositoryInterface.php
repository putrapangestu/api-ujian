<?php

namespace App\Repositories;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Collection;

/**
 * Exam Repository Interface
 * 
 * Interface ini mendefinisikan kontrak untuk ExamRepository
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface ExamRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get active exams
     * 
     * @return Collection
     */
    public function getActiveExams(): Collection;

    /**
     * Search exams by title or description
     * 
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection;

    /**
     * Get exams with question count
     * 
     * @return Collection
     */
    public function getWithQuestionCount(): Collection;

    /**
     * Get exams by passing score range
     * 
     * @param int $minScore
     * @param int $maxScore
     * @return Collection
     */
    public function getByPassingScoreRange(int $minScore, int $maxScore): Collection;
}