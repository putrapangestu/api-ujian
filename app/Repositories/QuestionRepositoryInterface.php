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
     * @param int $id
     * @return Question|null
     */
    public function findById(int $id): ?Question;

    /**
     * Get all questions
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll();

    /**
     * Get questions by exam ID
     *
     * @param int $examId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByExamId(int $examId);

    /**
     * Get questions by question group ID
     *
     * @param int $questionGroupId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByQuestionGroupId(int $questionGroupId);

    /**
     * Get questions by category ID
     *
     * @param int $categoryId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByCategoryId(int $categoryId);

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
     * @param int $id
     * @param array $data
     * @return Question|null
     */
    public function update(int $id, array $data): ?Question;

    /**
     * Delete question
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get questions with options
     *
     * @param int $questionId
     * @return Question|null
     */
    public function getWithOptions(int $questionId): ?Question;

    /**
     * Count questions by exam ID
     *
     * @param int $examId
     * @return int
     */
    public function countByExamId(int $examId): int;

    /**
     * Count questions by question group ID
     *
     * @param int $questionGroupId
     * @return int
     */
    public function countByQuestionGroupId(int $questionGroupId): int;

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