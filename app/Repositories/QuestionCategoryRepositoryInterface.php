<?php

namespace App\Repositories;

use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Collection;

/**
 * QuestionCategory Repository Interface
 * 
 * Interface ini mendefinisikan kontrak untuk QuestionCategoryRepository
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface QuestionCategoryRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get active categories
     * 
     * @return Collection
     */
    public function getActiveCategories(): Collection;

    /**
     * Get categories with question count
     * 
     * @return Collection
     */
    public function getWithQuestionCount(): Collection;

    /**
     * Find category by code
     * 
     * @param string $code
     * @return QuestionCategory|null
     */
    public function findByCode(string $code): ?QuestionCategory;

    /**
     * Search categories by name or code
     * 
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection;
}