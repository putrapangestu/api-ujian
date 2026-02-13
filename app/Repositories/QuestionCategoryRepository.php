<?php

namespace App\Repositories;

use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Collection;

/**
 * QuestionCategory Repository Class
 * 
 * Repository ini menangani semua operasi database terkait QuestionCategory model
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
class QuestionCategoryRepository extends BaseRepository implements QuestionCategoryRepositoryInterface
{
    /**
     * QuestionCategoryRepository constructor
     * 
     * @param QuestionCategory $model
     */
    public function __construct(QuestionCategory $model)
    {
        parent::__construct($model);
    }

    /**
     * Get active categories
     * 
     * @return Collection
     */
    public function getActiveCategories(): Collection
    {
        return $this->model->where('status', QuestionCategory::STATUS_ACTIVE)->get();
    }

    /**
     * Get categories with question count
     * 
     * @return Collection
     */
    public function getWithQuestionCount(): Collection
    {
        return $this->model->withCount('questions')->get();
    }

    /**
     * Find category by code
     * 
     * @param string $code
     * @return QuestionCategory|null
     */
    public function findByCode(string $code): ?QuestionCategory
    {
        return $this->model->where('code', $code)->first();
    }

    /**
     * Search categories by name or code
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
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->limit($limit)
            ->get();
    }
}