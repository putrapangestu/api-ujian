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
     * @param int $id
     * @return UserExam|null
     */
    public function findById(int $id): ?UserExam;

    /**
     * Find user exam by exam ID and student ID
     *
     * @param int $examId
     * @param int $studentId
     * @return UserExam|null
     */
    public function findByExamAndStudent(int $examId, int $studentId): ?UserExam;

    /**
     * Find all user exams by exam ID
     *
     * @param int $examId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByExamId(int $examId);

    /**
     * Find all user exams by student ID
     *
     * @param int $studentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByStudentId(int $studentId);

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
     * @param int $id
     * @param array $data
     * @return UserExam|null
     */
    public function update(int $id, array $data): ?UserExam;

    /**
     * Delete user exam
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get user exams with answers
     *
     * @param int $userExamId
     * @return UserExam|null
     */
    public function getWithAnswers(int $userExamId): ?UserExam;

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
     * @param int $examId
     * @return int
     */
    public function countByExamId(int $examId): int;

    /**
     * Count user exams by student ID
     *
     * @param int $studentId
     * @return int
     */
    public function countByStudentId(int $studentId): int;

    /**
     * Get average score by exam ID
     *
     * @param int $examId
     * @return float|null
     */
    public function getAverageScoreByExamId(int $examId): ?float;

    /**
     * Get highest score by exam ID
     *
     * @param int $examId
     * @return int|null
     */
    public function getHighestScoreByExamId(int $examId): ?int;

    /**
     * Get lowest score by exam ID
     *
     * @param int $examId
     * @return int|null
     */
    public function getLowestScoreByExamId(int $examId): ?int;
}