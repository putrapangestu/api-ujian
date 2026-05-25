<?php

namespace App\Services;

use App\Models\ExamMonitoring;
use App\Models\UserExam;
use App\Models\Student;
use App\Models\Exam;
use App\Models\QuestionGroup;
use App\Models\QuestionCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
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

            $this->emitEvent('exam-monitoring', 'exam_action', $data, $this->examRoom($monitoring->exam_id));

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

            $this->emitEvent('exam-completion', 'exam_completed', $data, $this->examRoom($userExam->exam_id));

            return $this->successResponse($data, 'Completion broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data completion: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast group monitoring data
     *
     * @param string $examId
     * @param string $questionGroupId
     * @param array $statistics
     * @return array
     */
    public function broadcastGroupMonitoring(string $examId, string $questionGroupId, array $statistics): array
    {
        try {
            $data = $this->prepareGroupMonitoringData($examId, $questionGroupId, $statistics);

            $this->emitEvent('group-monitoring', 'group_statistics_updated', $data, $this->examRoom($examId));

            return $this->successResponse($data, 'Group monitoring broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data group monitoring: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast category monitoring data
     *
     * @param string $examId
     * @param array $categories
     * @return array
     */
    public function broadcastCategoryMonitoring(string $examId, array $categories): array
    {
        try {
            $data = $this->prepareCategoryMonitoringData($examId, $categories);

            $this->emitEvent('category-monitoring', 'category_statistics_updated', $data, $this->examRoom($examId));

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

            $this->emitEvent('student-rolling', 'student_rolled', $data, $this->studentRoom($userExam->student_id));

            return $this->successResponse($data, 'Student rolling broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data student rolling: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast real-time exam statistics
     *
     * @param string $examId
     * @param array $statistics
     * @return array
     */
    public function broadcastExamStatistics(string $examId, array $statistics): array
    {
        try {
            $data = [
                'exam_id' => $examId,
                'statistics' => $statistics,
                'timestamp' => Carbon::now()->toISOString(),
            ];

            $this->emitEvent('exam-statistics', 'statistics_updated', $data, $this->examRoom($examId));

            return $this->successResponse($data, 'Exam statistics broadcast prepared');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data exam statistics: ' . $e->getMessage());
        }
    }

    public function broadcastCheatingDetected(ExamMonitoring $monitoring, array $cheating): array
    {
        try {
            $data = [
                'exam_id' => $monitoring->exam_id,
                'user_exam_id' => $monitoring->user_exam_id,
                'student_id' => $monitoring->student_id,
                'reason' => $cheating,
                'flagged_at' => Carbon::now()->toISOString(),
            ];

            $this->emitEvent('cheating-monitoring', 'cheating_detected', $data, $this->examRoom($monitoring->exam_id));

            return $this->successResponse($data, 'Cheating broadcast prepared');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mempersiapkan data cheating: ' . $e->getMessage());
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
     * @param string $examId
     * @param string $questionGroupId
     * @param array $statistics
     * @return array
     */
    private function prepareGroupMonitoringData(string $examId, string $questionGroupId, array $statistics): array
    {
        return [
            'exam_id' => $examId,
            'question_group_id' => $questionGroupId,
            'question_group' => ($questionGroup = QuestionGroup::find($questionGroupId)) ? [
                'id' => $questionGroup->id,
                'name' => $questionGroup->name,
                'code' => $questionGroup->code,
            ] : null,
            'statistics' => $statistics,
            'timestamp' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Prepare category monitoring data
     *
     * @param string $examId
     * @param array $categories
     * @return array
     */
    private function prepareCategoryMonitoringData(string $examId, array $categories): array
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
            'timestamp' => Carbon::now()->toISOString(),
        ];
    }

    public function emitEvent(string $channel, string $event, array $data, ?string $room = null): bool
    {
        $url = config('services.socket_io.url');
        if (!$url) {
            return false;
        }

        $request = Http::timeout(2)->acceptJson();
        $key = config('services.socket_io.key');
        if ($key) {
            $request = $request->withHeaders(['x-socket-key' => $key]);
        }

        $response = $request->post(rtrim($url, '/') . '/emit', [
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
            'room' => $room,
        ]);

        if ($response->failed()) {
            Log::warning('Socket.IO emit failed', [
                'channel' => $channel,
                'event' => $event,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    private function examRoom(string $examId): string
    {
        return "exam:{$examId}";
    }

    private function studentRoom(string $studentId): string
    {
        return "student:{$studentId}";
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
            'rolled_at' => Carbon::now()->toISOString(),
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
