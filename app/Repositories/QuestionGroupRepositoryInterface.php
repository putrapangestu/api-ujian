<?php

namespace App\Repositories;

use App\Models\QuestionGroup;
use Illuminate\Database\Eloquent\Collection;

/**
 * QuestionGroup Repository Interface
 *
 * Interface ini mendefinisikan kontrak untuk QuestionGroupRepository
 *
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface QuestionGroupRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get active question groups
     *
     * @return Collection
     */
    public function getActiveGroups(): Collection;

    /**
     * Get question groups with question count
     *
     * @return Collection
     */
    public function getWithQuestionCount(): Collection;

    /**
     * Find question group by code
     *
     * @param string $code
     * @return QuestionGroup|null
     */
    public function findByCode(string $code): ?QuestionGroup;

    /**
     * Search question groups by name or code
     *
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection;

    /**
     * Get question groups for exam
     *
     * @param string $examId
     * @return Collection
     */
    public function getForExam(string $examId): Collection;

    /**
     * Import question group from file
     *
     * @param array $data
     * @return array
     */
    public function importGroup(array $data): array;
}
