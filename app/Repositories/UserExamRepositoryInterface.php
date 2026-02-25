<?php

namespace App\Repositories;

use App\Models\UserExam;

/**
 * UserExam Repository Interface
 *
 * Interface ini mendefinisikan kontrak untuk UserExam repository
 *
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface UserExamRepositoryInterface
{
    /**
     * Find user exam by ID
     *
     * @param string $id
     * @return UserExam|null
     */
    public function findById(string $id): ?UserExam;

    /**
     * Find user exam by exam ID and student ID
     *
     * @param string $examId
     * @param string $studentId
     * @return UserExam|null
     */
    public function findByExamAndStudent(string $examId, string $studentId): ?UserExam;

    /**
     * Find all user exams by exam ID
     *
     * @param string $examId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByExamId(string $examId);

    /**
     * Find all user exams by student ID
     *
     * @param string $studentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByStudentId(string $studentId);

    /**
     * Create new user exam
     *
     * @param array $data
     * @return UserExam
     */
    public function create(array $data): UserExam;

    /**
     * Update user exam
     *
     * @param string $id
     * @param array $data
     * @return UserExam|null
     */
    public function update(string $id, array $data): ?UserExam;

    /**
     * Delete user exam
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Get user exams with answers
     *
     * @param string $userExamId
     * @return UserExam|null
     */
    public function getWithAnswers(string $userExamId): ?UserExam;

    /**
     * Get active user exams (in progress)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveExams();

    /**
     * Get completed user exams
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCompletedExams();

    /**
     * Get user exams by status
     *
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByStatus(string $status);

    /**
     * Get user exams with related data
     *
     * @param array $relations
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWithRelations(array $relations);

    /**
     * Count user exams by exam ID
     *
     * @param string $examId
     * @return int
     */
    public function countByExamId(string $examId): int;

    /**
     * Count user exams by student ID
     *
     * @param string $studentId
     * @return int
     */
    public function countByStudentId(string $studentId): int;

    /**
     * Get average score by exam ID
     *
     * @param string $examId
     * @return float|null
     */
    public function getAverageScoreByExamId(string $examId): ?float;

    /**
     * Get highest score by exam ID
     *
     * @param string $examId
     * @return int|null
     */
    public function getHighestScoreByExamId(string $examId): ?int;

    /**
     * Get lowest score by exam ID
     *
     * @param string $examId
     * @return int|null
     */
    public function getLowestScoreByExamId(string $examId): ?int;
}
