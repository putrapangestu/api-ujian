<?php

namespace App\Repositories;

use App\Models\UserExam;
use Illuminate\Database\Eloquent\Collection;

/**
 * UserExam Repository Implementation
 *
 * Repository ini mengimplementasikan UserExamRepositoryInterface
 *
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
class UserExamRepository implements UserExamRepositoryInterface
{
    /**
     * Find user exam by ID
     *
     * @param string $id
     * @return UserExam|null
     */
    public function findById(string $id): ?UserExam
    {
        return UserExam::find($id);
    }

    /**
     * Find user exam by exam ID and student ID
     *
     * @param int $examId
     * @param int $studentId
     * @return UserExam|null
     */
    public function findByExamAndStudent(int $examId, int $studentId): ?UserExam
    {
        return UserExam::where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->first();
    }

    /**
     * Find all user exams by exam ID
     *
     * @param int $examId
     * @return Collection
     */
    public function findByExamId(int $examId)
    {
        return UserExam::where('exam_id', $examId)->get();
    }

    /**
     * Find all user exams by student ID
     *
     * @param int $studentId
     * @return Collection
     */
    public function findByStudentId(int $studentId)
    {
        return UserExam::where('student_id', $studentId)->get();
    }

    /**
     * Create new user exam
     *
     * @param array $data
     * @return UserExam
     */
    public function create(array $data): UserExam
    {
        return UserExam::create($data);
    }

    /**
     * Update user exam
     *
     * @param string $id
     * @param array $data
     * @return UserExam|null
     */
    public function update(string $id, array $data): ?UserExam
    {
        $userExam = $this->findById($id);

        if ($userExam) {
            $userExam->update($data);
            return $userExam;
        }

        return null;
    }

    /**
     * Delete user exam
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        $userExam = $this->findById($id);

        if ($userExam) {
            return $userExam->delete();
        }

        return false;
    }

    /**
     * Get user exams with answers
     *
     * @param int $userExamId
     * @return UserExam|null
     */
    public function getWithAnswers(int $userExamId): ?UserExam
    {
        return UserExam::with(['answers', 'answers.question', 'answers.questionOption'])
            ->find($userExamId);
    }

    /**
     * Get active user exams (in progress)
     *
     * @return Collection
     */
    public function getActiveExams()
    {
        return UserExam::where('status', UserExam::STATUS_IN_PROGRESS)->get();
    }

    /**
     * Get completed user exams
     *
     * @return Collection
     */
    public function getCompletedExams()
    {
        return UserExam::where('status', UserExam::STATUS_COMPLETED)->get();
    }

    /**
     * Get user exams by status
     *
     * @param string $status
     * @return Collection
     */
    public function getByStatus(string $status)
    {
        return UserExam::where('status', $status)->get();
    }

    /**
     * Get user exams with related data
     *
     * @param array $relations
     * @return Collection
     */
    public function getWithRelations(array $relations)
    {
        return UserExam::with($relations)->get();
    }

    /**
     * Count user exams by exam ID
     *
     * @param int $examId
     * @return int
     */
    public function countByExamId(int $examId): int
    {
        return UserExam::where('exam_id', $examId)->count();
    }

    /**
     * Count user exams by student ID
     *
     * @param int $studentId
     * @return int
     */
    public function countByStudentId(int $studentId): int
    {
        return UserExam::where('student_id', $studentId)->count();
    }

    /**
     * Get average score by exam ID
     *
     * @param int $examId
     * @return float|null
     */
    public function getAverageScoreByExamId(int $examId): ?float
    {
        return UserExam::where('exam_id', $examId)
            ->whereNotNull('score')
            ->avg('score');
    }

    /**
     * Get highest score by exam ID
     *
     * @param int $examId
     * @return int|null
     */
    public function getHighestScoreByExamId(int $examId): ?int
    {
        return UserExam::where('exam_id', $examId)
            ->whereNotNull('score')
            ->max('score');
    }

    /**
     * Get lowest score by exam ID
     *
     * @param int $examId
     * @return int|null
     */
    public function getLowestScoreByExamId(int $examId): ?int
    {
        return UserExam::where('exam_id', $examId)
            ->whereNotNull('score')
            ->min('score');
    }
}
