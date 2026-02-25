<?php

namespace App\Services;

use App\Models\ExamMonitoring;
use App\Models\UserExam;
use App\Models\QuestionGroup;
use App\Repositories\StudentRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExamMonitoring Service Class
 *
 * Service ini menangani monitoring realtime pengerjaan ujian
 * Menggunakan Socket.IO untuk realtime updates
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class ExamMonitoringService extends BaseService
{
    /**
     * @var StudentRepositoryInterface
     */
    protected StudentRepositoryInterface $studentRepository;

    /**
     * ExamMonitoringService constructor
     *
     * @param StudentRepositoryInterface $studentRepository
     */
    public function __construct(StudentRepositoryInterface $studentRepository)
    {
        $this->studentRepository = $studentRepository;
    }

    /**
     * Record exam action for monitoring
     *
     * @param array $data
     * @return array
     */
    public function recordAction(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                // Validate required data
                $rules = [
                    'user_exam_id' => 'required|exists:user_exams,id',
                    'student_id' => 'required|exists:students,id',
                    'exam_id' => 'required|exists:exams,id',
                    'action' => 'required|in:start,answer,navigate,submit,pause,resume',
                    'question_id' => 'nullable|exists:questions,id',
                    'answer' => 'nullable|string',
                    'time_spent' => 'nullable|integer|min:0',
                ];

                $this->validateData($data, $rules);

                // Create monitoring record
                $monitoring = ExamMonitoring::create($data);

                // Broadcast to Socket.IO if configured
                $this->broadcastToSocket($monitoring);

                return $this->successResponse($monitoring, 'Aksi ujian berhasil direkam', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal merekam aksi ujian: ' . $e->getMessage());
            }
        });
    }

    /**
     * Get real-time exam monitoring data
     *
     * @param string $examId
     * @return array
     */
    public function getRealtimeMonitoring(string $examId): array
    {
        try {
            // Get active user exams for this exam
            $activeExams = UserExam::where('exam_id', $examId)
                ->where('status', UserExam::STATUS_IN_PROGRESS)
                ->with(['student', 'exam'])
                ->get();

            $monitoringData = [];

            foreach ($activeExams as $userExam) {
                // Get latest monitoring data for this user exam
                $latestMonitoring = ExamMonitoring::where('user_exam_id', $userExam->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $monitoringData[] = [
                    'user_exam' => $userExam,
                    'latest_action' => $latestMonitoring,
                    'time_remaining' => $this->calculateTimeRemaining($userExam),
                    'progress' => $this->calculateProgress($userExam),
                ];
            }

            return $this->successResponse($monitoringData, 'Data monitoring realtime berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data monitoring: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed monitoring for specific student
     *
     * @param string $studentId
     * @param string $examId
     * @return array
     */
    public function getStudentMonitoring(string $studentId, string $examId): array
    {
        try {
            // Get user exam for this student and exam
            $userExam = UserExam::where('student_id', $studentId)
                ->where('exam_id', $examId)
                ->with(['student', 'exam'])
                ->first();

            if (!$userExam) {
                return $this->errorResponse('Ujian tidak ditemukan untuk siswa ini', null, 404);
            }

            // Get all monitoring records
            $monitoringRecords = ExamMonitoring::where('user_exam_id', $userExam->id)
                ->with(['question'])
                ->orderBy('created_at', 'asc')
                ->get();

            // Calculate statistics
            $statistics = $this->calculateStatistics($monitoringRecords);

            return $this->successResponse([
                'user_exam' => $userExam,
                'monitoring_records' => $monitoringRecords,
                'statistics' => $statistics,
            ], 'Detail monitoring siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil detail monitoring siswa: ' . $e->getMessage());
        }
    }

    /**
     * Get exam summary for monitoring dashboard
     *
     * @param string $examId
     * @return array
     */
    public function getExamSummary(string $examId): array
    {
        try {
            // Get all user exams for this exam
            $userExams = UserExam::where('exam_id', $examId)
                ->with(['student'])
                ->get();

            $totalStudents = $userExams->count();
            $completedStudents = $userExams->where('status', UserExam::STATUS_COMPLETED)->count();
            $inProgressStudents = $userExams->where('status', UserExam::STATUS_IN_PROGRESS)->count();
            $notStartedStudents = $userExams->where('status', UserExam::STATUS_PENDING)->count();

            // Get average score
            $averageScore = $userExams->where('status', UserExam::STATUS_COMPLETED)->avg('score');

            // Get latest activities
            $latestActivities = ExamMonitoring::where('exam_id', $examId)
                ->with(['student', 'question'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return $this->successResponse([
                'total_students' => $totalStudents,
                'completed_students' => $completedStudents,
                'in_progress_students' => $inProgressStudents,
                'not_started_students' => $notStartedStudents,
                'average_score' => round($averageScore, 2),
                'latest_activities' => $latestActivities,
            ], 'Ringkasan ujian berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil ringkasan ujian: ' . $e->getMessage());
        }
    }

    /**
     * Get exam monitoring by question group
     *
     * @param string $examId
     * @param string $questionGroupId
     * @return array
     */
    public function getMonitoringByQuestionGroup(string $examId, string $questionGroupId): array
    {
        try {
            // Get question group
            $questionGroup = QuestionGroup::find($questionGroupId);
            if (!$questionGroup) {
                return $this->errorResponse('Grup soal tidak ditemukan', null, 404);
            }

            // Get questions in this group
            $questions = $questionGroup->questions()->where('exam_id', $examId)->get();
            $questionIds = $questions->pluck('id');

            // Get user exams for this exam
            $userExams = UserExam::where('exam_id', $examId)
                ->with(['student', 'userAnswers' => function ($query) use ($questionIds) {
                    $query->whereIn('question_id', $questionIds);
                }])
                ->get();

            $groupStats = [];
            $totalCorrect = 0;
            $totalWrong = 0;
            $totalScore = 0;

            foreach ($userExams as $userExam) {
                $correctAnswers = 0;
                $wrongAnswers = 0;
                $groupScore = 0;

                foreach ($userExam->userAnswers as $answer) {
                    if ($answer->is_correct) {
                        $correctAnswers++;
                        $totalCorrect++;
                    } else {
                        $wrongAnswers++;
                        $totalWrong++;
                    }
                    $groupScore += $answer->score;
                    $totalScore += $answer->score;
                }

                $groupStats[] = [
                    'student' => $userExam->student,
                    'correct_answers' => $correctAnswers,
                    'wrong_answers' => $wrongAnswers,
                    'score' => $groupScore,
                    'completion_percentage' => $questions->count() > 0 ? round(($correctAnswers + $wrongAnswers) / $questions->count() * 100, 2) : 0,
                ];
            }

            $totalStudents = $userExams->count();
            $averageScore = $totalStudents > 0 ? round($totalScore / $totalStudents, 2) : 0;
            $averageCorrect = $totalStudents > 0 ? round($totalCorrect / $totalStudents, 2) : 0;

            return $this->successResponse([
                'question_group' => $questionGroup,
                'total_questions' => $questions->count(),
                'total_students' => $totalStudents,
                'statistics' => [
                    'total_correct' => $totalCorrect,
                    'total_wrong' => $totalWrong,
                    'average_correct_per_student' => $averageCorrect,
                    'average_score' => $averageScore,
                ],
                'student_stats' => $groupStats,
            ], 'Monitoring per grup soal berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil monitoring per grup soal: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed monitoring by category
     *
     * @param string $examId
     * @return array
     */
    public function getMonitoringByCategory(string $examId): array
    {
        try {
            // Get all categories used in this exam
            $categories = \App\Models\QuestionCategory::whereHas('questions', function ($query) use ($examId) {
                $query->where('exam_id', $examId);
            })->with(['questions' => function ($query) use ($examId) {
                $query->where('exam_id', $examId);
            }])->get();

            $categoryStats = [];

            foreach ($categories as $category) {
                $questionIds = $category->questions->pluck('id');

                // Get answers for this category
                $answers = \App\Models\UserAnswer::whereIn('question_id', $questionIds)
                    ->whereHas('userExam', function ($query) use ($examId) {
                        $query->where('exam_id', $examId);
                    })
                    ->get();

                $totalAnswers = $answers->count();
                $correctAnswers = $answers->where('is_correct', true)->count();
                $totalScore = $answers->sum('score');
                $averageScore = $totalAnswers > 0 ? round($totalScore / $answers->count(), 2) : 0;
                $accuracy = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 2) : 0;

                $categoryStats[] = [
                    'category' => $category,
                    'total_questions' => $questionIds->count(),
                    'total_answers' => $totalAnswers,
                    'correct_answers' => $correctAnswers,
                    'wrong_answers' => $totalAnswers - $correctAnswers,
                    'accuracy_percentage' => $accuracy,
                    'total_score' => $totalScore,
                    'average_score' => $averageScore,
                ];
            }

            return $this->successResponse([
                'categories' => $categoryStats,
                'total_categories' => $categories->count(),
            ], 'Monitoring per kategori berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil monitoring per kategori: ' . $e->getMessage());
        }
    }

    /**
     * Calculate time remaining for exam
     *
     * @param UserExam $userExam
     * @return int
     */
    private function calculateTimeRemaining(UserExam $userExam): int
    {
        if (!$userExam->start_time) {
            return 0;
        }

        $exam = $userExam->exam;
        $durationInSeconds = $exam->duration * 60;
        $elapsedTime = Carbon::now()->diffInSeconds($userExam->start_time);
        $remainingTime = $durationInSeconds - $elapsedTime;

        return max(0, $remainingTime);
    }

    /**
     * Calculate progress percentage
     *
     * @param UserExam $userExam
     * @return float
     */
    private function calculateProgress(UserExam $userExam): float
    {
        $totalQuestions = $userExam->exam->total_questions;
        $answeredQuestions = $userExam->userAnswers()->count();

        if ($totalQuestions == 0) {
            return 0;
        }

        return round(($answeredQuestions / $totalQuestions) * 100, 2);
    }

    /**
     * Calculate statistics from monitoring records
     *
     * @param \Illuminate\Database\Eloquent\Collection $records
     * @return array
     */
    private function calculateStatistics($records): array
    {
        $totalActions = $records->count();
        $answerActions = $records->where('action', ExamMonitoring::ACTION_ANSWER)->count();
        $timeSpent = $records->sum('time_spent');

        $questionStats = [];
        foreach ($records->where('question_id', '!=', null)->groupBy('question_id') as $questionId => $questionRecords) {
            $questionStats[$questionId] = [
                'attempts' => $questionRecords->count(),
                'time_spent' => $questionRecords->sum('time_spent'),
                'last_answer' => $questionRecords->last()->answer ?? null,
            ];
        }

        return [
            'total_actions' => $totalActions,
            'total_answers' => $answerActions,
            'total_time_spent' => $timeSpent,
            'average_time_per_question' => $answerActions > 0 ? round($timeSpent / $answerActions, 2) : 0,
            'question_statistics' => $questionStats,
        ];
    }

    /**
     * Broadcast monitoring data to Socket.IO
     *
     * @param ExamMonitoring $monitoring
     * @return void
     */
    private function broadcastToSocket(ExamMonitoring $monitoring): void
    {
        // This will be implemented when Socket.IO is configured
        // For now, we'll just prepare the data structure
        $broadcastData = [
            'type' => 'exam_monitoring',
            'data' => [
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
                ] : null,
            ],
            'timestamp' => now()->toISOString(),
        ];

        // Log the broadcast data (will be replaced with actual Socket.IO broadcast)
        Log::info('Socket.IO Broadcast:', $broadcastData);
    }
}
