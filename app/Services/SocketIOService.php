<?php

namespace App\Services;

use App\Models\ExamMonitoring;
use App\Models\UserExam;
use App\Models\Student;
use App\Models\Exam;
use App\Models\QuestionGroup;
use App\Models\QuestionCategory;
use Illuminate\Support\Facades\Log;

/**
 * SocketIO Service Class
 * 
 * Service ini menangani integrasi dengan Socket.IO untuk realtime monitoring
 * 
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class SocketIOService extends BaseService
{
    /**
     * Broadcast exam monitoring data
     * 
     * @param ExamMonitoring $monitoring
     * @return array
     */
    public function broadcastExamMonitoring(ExamMonitoring $monitoring): array
    {
        try {
            $data = $this->prepareExamMonitoringData($monitoring);
            
            // Log the broadcast (will be replaced with actual Socket.IO broadcast)
            Log::info('Socket.IO Broadcast - Exam Monitoring', [
                'channel' => 'exam-monitoring',
                'event' => 'exam_action',
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

            return $this->successResponse($data, 'Broadcast data prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data broadcast: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast exam completion
     * 
     * @param UserExam $userExam
     * @return array
     */
    public function broadcastExamCompletion(UserExam $userExam): array
    {
        try {
            $data = $this->prepareExamCompletionData($userExam);
            
            // Log the broadcast (will be replaced with actual Socket.IO broadcast)
            Log::info('Socket.IO Broadcast - Exam Completion', [
                'channel' => 'exam-completion',
                'event' => 'exam_completed',
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

            return $this->successResponse($data, 'Completion broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data completion: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast group monitoring data
     * 
     * @param int $examId
     * @param int $questionGroupId
     * @param array $statistics
     * @return array
     */
    public function broadcastGroupMonitoring(int $examId, int $questionGroupId, array $statistics): array
    {
        try {
            $data = $this->prepareGroupMonitoringData($examId, $questionGroupId, $statistics);
            
            // Log the broadcast (will be replaced with actual Socket.IO broadcast)
            Log::info('Socket.IO Broadcast - Group Monitoring', [
                'channel' => 'group-monitoring',
                'event' => 'group_statistics_updated',
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

            return $this->successResponse($data, 'Group monitoring broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data group monitoring: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast category monitoring data
     * 
     * @param int $examId
     * @param array $categories
     * @return array
     */
    public function broadcastCategoryMonitoring(int $examId, array $categories): array
    {
        try {
            $data = $this->prepareCategoryMonitoringData($examId, $categories);
            
            // Log the broadcast (will be replaced with actual Socket.IO broadcast)
            Log::info('Socket.IO Broadcast - Category Monitoring', [
                'channel' => 'category-monitoring',
                'event' => 'category_statistics_updated',
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

            return $this->successResponse($data, 'Category monitoring broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data category monitoring: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast student rolling event
     * 
     * @param UserExam $userExam
     * @param array $rollData
     * @return array
     */
    public function broadcastStudentRolling(UserExam $userExam, array $rollData): array
    {
        try {
            $data = $this->prepareStudentRollingData($userExam, $rollData);
            
            // Log the broadcast (will be replaced with actual Socket.IO broadcast)
            Log::info('Socket.IO Broadcast - Student Rolling', [
                'channel' => 'student-rolling',
                'event' => 'student_rolled',
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

            return $this->successResponse($data, 'Student rolling broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data student rolling: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast real-time exam statistics
     * 
     * @param int $examId
     * @param array $statistics
     * @return array
     */
    public function broadcastExamStatistics(int $examId, array $statistics): array
    {
        try {
            $data = [
                'exam_id' => $examId,
                'statistics' => $statistics,
                'timestamp' => now()->toISOString(),
            ];
            
            // Log the broadcast (will be replaced with actual Socket.IO broadcast)
            Log::info('Socket.IO Broadcast - Exam Statistics', [
                'channel' => 'exam-statistics',
                'event' => 'statistics_updated',
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

            return $this->successResponse($data, 'Exam statistics broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data exam statistics: ' . $e->getMessage());
        }
    }

    /**
     * Prepare exam monitoring data
     * 
     * @param ExamMonitoring $monitoring
     * @return array
     */
    private function prepareExamMonitoringData(ExamMonitoring $monitoring): array
    {
        return [
            'id' => $monitoring->id,
            'user_exam_id' => $monitoring->user_exam_id,
            'student_id' => $monitoring->student_id,
            'exam_id' => $monitoring->exam_id,
            'action' => $monitoring->action,
            'question_id' => $monitoring->question_id,
            'answer' => $monitoring->answer,
            'time_spent' => $monitoring->time_spent,
            'created_at' => $monitoring->created_at->toISOString(),
            'student' => $monitoring->student ? [
                'id' => $monitoring->student->id,
                'name' => $monitoring->student->name,
                'nisn' => $monitoring->student->nisn,
                'group' => $monitoring->student->group ? [
                    'id' => $monitoring->student->group->id,
                    'name' => $monitoring->student->group->name,
                ] : null,
            ] : null,
            'question' => $monitoring->question ? [
                'id' => $monitoring->question->id,
                'text' => $monitoring->question->question_text,
                'category' => $monitoring->question->category ? [
                    'id' => $monitoring->question->category->id,
                    'name' => $monitoring->question->category->name,
                ] : null,
                'group' => $monitoring->question->questionGroup ? [
                    'id' => $monitoring->question->questionGroup->id,
                    'name' => $monitoring->question->questionGroup->name,
                ] : null,
            ] : null,
        ];
    }

    /**
     * Prepare exam completion data
     * 
     * @param UserExam $userExam
     * @return array
     */
    private function prepareExamCompletionData(UserExam $userExam): array
    {
        return [
            'user_exam_id' => $userExam->id,
            'student_id' => $userExam->student_id,
            'exam_id' => $userExam->exam_id,
            'score' => $userExam->score,
            'passed' => $userExam->isPassed(),
            'completed_at' => $userExam->end_time->toISOString(),
            'duration_seconds' => $userExam->start_time->diffInSeconds($userExam->end_time),
            'student' => $userExam->student ? [
                'id' => $userExam->student->id,
                'name' => $userExam->student->name,
                'nisn' => $userExam->student->nisn,
                'group' => $userExam->student->group ? [
                    'id' => $userExam->student->group->id,
                    'name' => $userExam->student->group->name,
                ] : null,
            ] : null,
            'exam' => $userExam->exam ? [
                'id' => $userExam->exam->id,
                'title' => $userExam->exam->title,
                'passing_score' => $userExam->exam->passing_score,
            ] : null,
        ];
    }

    /**
     * Prepare group monitoring data
     * 
     * @param int $examId
     * @param int $questionGroupId
     * @param array $statistics
     * @return array
     */
    private function prepareGroupMonitoringData(int $examId, int $questionGroupId, array $statistics): array
    {
        return [
            'exam_id' => $examId,
            'question_group_id' => $questionGroupId,
            'question_group' => QuestionGroup::find($questionGroupId) ? [
                'id' => QuestionGroup::find($questionGroupId)->id,
                'name' => QuestionGroup::find($questionGroupId)->name,
                'code' => QuestionGroup::find($questionGroupId)->code,
            ] : null,
            'statistics' => $statistics,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Prepare category monitoring data
     * 
     * @param int $examId
     * @param array $categories
     * @return array
     */
    private function prepareCategoryMonitoringData(int $examId, array $categories): array
    {
        return [
            'exam_id' => $examId,
            'categories' => array_map(function ($category) {
                return [
                    'category' => $category['category'] ? [
                        'id' => $category['category']->id,
                        'name' => $category['category']->name,
                        'code' => $category['category']->code,
                    ] : null,
                    'total_questions' => $category['total_questions'],
                    'total_answers' => $category['total_answers'],
                    'correct_answers' => $category['correct_answers'],
                    'wrong_answers' => $category['wrong_answers'],
                    'accuracy_percentage' => $category['accuracy_percentage'],
                    'total_score' => $category['total_score'],
                    'average_score' => $category['average_score'],
                ];
            }, $categories),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Prepare student rolling data
     * 
     * @param UserExam $userExam
     * @param array $rollData
     * @return array
     */
    private function prepareStudentRollingData(UserExam $userExam, array $rollData): array
    {
        return [
            'user_exam_id' => $userExam->id,
            'student_id' => $userExam->student_id,
            'exam_id' => $userExam->exam_id,
            'old_group' => $rollData['old_group'],
            'new_group' => $rollData['new_group'],
            'rolled_at' => now()->toISOString(),
            'student' => $userExam->student ? [
                'id' => $userExam->student->id,
                'name' => $userExam->student->name,
                'nisn' => $userExam->student->nisn,
            ] : null,
            'exam' => $userExam->exam ? [
                'id' => $userExam->exam->id,
                'title' => $userExam->exam->title,
            ] : null,
        ];
    }
}