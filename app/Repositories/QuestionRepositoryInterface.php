<?php

namespace App\Repositories;

use App\Models\Question;

/**
 * Question Repository Interface
 *
 * Interface ini mendefinisikan kontrak untuk Question repository
 *
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface QuestionRepositoryInterface
{
    /**
     * Find question by ID
     *
     * @param string $id
     * @return Question|null
     */
    public function findById(string $id): ?Question;

    /**
     * Get all questions
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll();

    /**
     * Get questions by exam ID
     *
     * @param string $examId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByExamId(string $examId);

    /**
     * Get questions by question group ID
     *
     * @param string $questionGroupId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByQuestionGroupId(string $questionGroupId);

    /**
     * Get questions by category ID
     *
     * @param string $categoryId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByCategoryId(string $categoryId);

    /**
     * Get active questions
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveQuestions();

    /**
     * Create new question
     *
     * @param array $data
     * @return Question
     */
    public function create(array $data): Question;

    /**
     * Update question
     *
     * @param string $id
     * @param array $data
     * @return Question|null
     */
    public function update(string $id, array $data): ?Question;

    /**
     * Delete question
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Get questions with options
     *
     * @param string $questionId
     * @return Question|null
     */
    public function getWithOptions(string $questionId): ?Question;

    /**
     * Count questions by exam ID
     *
     * @param string $examId
     * @return int
     */
    public function countByExamId(string $examId): int;

    /**
     * Count questions by question group ID
     *
     * @param string $questionGroupId
     * @return int
     */
    public function countByQuestionGroupId(string $questionGroupId): int;

    /**
     * Get questions with related data
     *
     * @param array $relations
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWithRelations(array $relations);

    /**
     * Search questions
     *
     * @param string $keyword
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function search(string $keyword);
}
