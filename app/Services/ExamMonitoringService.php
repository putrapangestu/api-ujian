<?php

namespace App\Services;

use App\Models\ExamMonitoring;
use App\Models\UserExam;
use App\Models\QuestionGroup;
use App\Repositories\StudentRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
    protected SocketIOService $socketIOService;

    /**
     * ExamMonitoringService constructor
     *
     * @param StudentRepositoryInterface $studentRepository
     * @param SocketIOService $socketIOService
     */
    public function __construct(StudentRepositoryInterface $studentRepository, SocketIOService $socketIOService)
    {
        $this->studentRepository = $studentRepository;
        $this->socketIOService = $socketIOService;
    }

    /**
     * Mengambil daftar ujian yang sedang aktif (ONGOING) untuk keperluan monitoring
     *
     * @return array
     */
    public function getActiveExams(): array
    {
        try {
            $now = \Carbon\Carbon::now();
            $exams = \App\Models\Exam::where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->withCount('userExams as participant_count')
                ->orderBy('start_date', 'desc')
                ->get()
                ->map(function ($exam) {
                    return [
                        'id' => $exam->id,
                        'title' => $exam->title,
                        'description' => $exam->description,
                        'start_date' => $exam->start_date,
                        'end_date' => $exam->end_date,
                        'duration' => $exam->duration,
                        'participant_count' => $exam->participant_count,
                        'status' => 'ongoing'
                    ];
                });

            return $this->successResponse($exams, 'Daftar ujian aktif berhasil diambil');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ExamMonitoringService getActiveExams Error: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil daftar ujian aktif: ' . $e->getMessage());
        }
    }

    /**
     * Menerima sinkronisasi data bulk dari Node.js WebSocket Server
     *
     * @param array $answers
     * @param array $monitorings
     * @param array $cheatCounters
     * @return array
     */
    public function processBulkSync(array $answers, array $monitorings, array $cheatCounters): array
    {
        return DB::transaction(function () use ($answers, $monitorings, $cheatCounters) {
            try {
                // 1. Bulk Insert Monitorings
                if (!empty($monitorings)) {
                    // Validasi atau persiapkan data monitoring jika perlu
                    $monitoringRows = array_map(function ($monitoring) {
                        return array_merge($monitoring, [
                            'created_at' => $monitoring['created_at'] ?? now(),
                            'updated_at' => $monitoring['updated_at'] ?? now(),
                        ]);
                    }, $monitorings);

                    ExamMonitoring::insert($monitoringRows);
                }

                // 2. Process Answers (Upsert)
                $userExamIds = [];
                if (!empty($answers)) {
                    $answerRows = array_map(function ($answer) {
                        $isCorrect = false;
                        $score = 0;
                        $questionOptionId = null;

                        // Di sistem nyata, kita perlu memverifikasi jawaban dengan kunci jawaban di sini.
                        // Jika struktur database memungkinkan, panggil layanan yang dapat memvalidasi jawaban.
                        $question = \App\Models\Question::with('options')->find($answer['question_id']);
                        if ($question) {
                            if ($question->type === \App\Models\Question::TYPE_MULTIPLE_CHOICE || $question->type === \App\Models\Question::TYPE_TRUE_FALSE) {
                                // Pilihan ganda, answer berisi question_option_id
                                $questionOptionId = $answer['answer'];
                                $correctOption = $question->options->where('is_correct', true)->first();

                                if ($correctOption && $correctOption->id == $questionOptionId) {
                                    $isCorrect = true;
                                    $score = $question->score ?? 1;
                                }
                            } else {
                                // Essay
                                $isCorrect = true; // Essay perlu dinilai manual nanti, asumsikan benar sementara
                                $score = 0; // Tidak langsung diberi nilai penuh
                            }
                        }

                        return [
                            'user_exam_id' => $answer['user_exam_id'],
                            'question_id' => $answer['question_id'],
                            'answer' => $answer['answer'],
                            'question_option_id' => $questionOptionId,
                            'is_correct' => $isCorrect,
                            'score' => $score,
                            'time_spent' => $answer['time_spent'] ?? 0,
                            'created_at' => $answer['created_at'] ?? now(),
                            'updated_at' => $answer['updated_at'] ?? now(),
                        ];
                    }, $answers);

                    \App\Models\UserAnswer::upsert(
                        $answerRows,
                        ['user_exam_id', 'question_id'], // unique keys (membutuhkan index unique di DB)
                        ['answer', 'question_option_id', 'is_correct', 'score', 'time_spent', 'updated_at'] // update columns
                    );
                    $userExamIds = array_unique(array_column($answers, 'user_exam_id'));
                }

                // 3. Recalculate current_score
                $updatedScores = [];
                foreach ($userExamIds as $userExamId) {
                    $totalScore = (int) \App\Models\UserAnswer::where('user_exam_id', $userExamId)->sum('score');
                    UserExam::where('id', $userExamId)->update(['current_score' => $totalScore]);
                    $updatedScores[$userExamId] = $totalScore;
                }

                // 4. (Optional) Process Cheat Counters if needed to DB
                // Saat ini cheat dihandle realtime, bisa ditambahkan jika ingin persistensi.

                // Pre-calculate total questions per category for these exams to avoid n+1
                $examIds = array_unique(array_column($answers, 'exam_id'));
                $examCategoriesTotal = [];
                foreach ($examIds as $examId) {
                    $examCategoriesTotal[$examId] = \App\Models\QuestionExam::where('exam_id', $examId)
                        ->join('questions', 'question_exam.question_id', '=', 'questions.id')
                        ->join('question_categories', 'questions.category_id', '=', 'question_categories.id')
                        ->select('question_categories.code', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                        ->groupBy('question_categories.code')
                        ->pluck('total', 'code')
                        ->toArray();
                }

                // 5. Emit back to Node.js
                foreach ($updatedScores as $userExamId => $score) {
                    $userExam = UserExam::with(['student.user', 'userAnswers.question.category'])->find($userExamId);
                    if ($userExam) {
                        // Calculate time spent
                        $timeSpent = 0;
                        if ($userExam->start_time) {
                            $endTime = $userExam->end_time ? \Carbon\Carbon::parse($userExam->end_time) : now();
                            $timeSpent = \Carbon\Carbon::parse($userExam->start_time)->diffInSeconds($endTime);
                        }

                        // Get category totals for this specific exam
                        $totals = $examCategoriesTotal[$userExam->exam_id] ?? [];

                        // Process categories
                        $categories = [
                            'TWK' => ['score' => 0, 'answered' => 0, 'total' => $totals['TWK'] ?? $totals['twk'] ?? 30],
                            'TIU' => ['score' => 0, 'answered' => 0, 'total' => $totals['TIU'] ?? $totals['tiu'] ?? 35],
                            'TKP' => ['score' => 0, 'answered' => 0, 'total' => $totals['TKP'] ?? $totals['tkp'] ?? 45]
                        ];

                        foreach ($userExam->userAnswers as $answer) {
                            if (!$answer->question || !$answer->question->category) continue;

                            $catCode = strtoupper($answer->question->category->code);

                            if (isset($categories[$catCode])) {
                                $categories[$catCode]['score'] += (int) $answer->score;
                                $categories[$catCode]['answered'] += 1;
                            }
                        }

                        $status = 'online';
                        if ($userExam->status === \App\Models\UserExam::STATUS_COMPLETED) {
                            $status = 'finished';
                        }

                        $payload = [
                            'id' => $userExam->student_id,
                            'update' => [
                                'timeRemaining' => $timeSpent,
                                'categories' => (object) $categories,
                                'totalScore' => $score,
                                'status' => $status
                            ]
                        ];

                        $this->socketIOService->emitEvent(
                            'exam:' . $userExam->exam_id,
                            'exam:progress_update',
                            $payload,
                            'exam:' . $userExam->exam_id
                        );
                    }
                }

                return ['success' => true];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Bulk Sync Error: ' . $e->getMessage());
                throw $e;
            }
        });
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
     * Get real-time exam monitoring data formatted for FE
     *
     * @param string $examId
     * @return array
     */
    public function getRealtimeMonitoring(string $examId): array
    {
        try {
            // Get active user exams for this exam
            $activeExams = UserExam::where('exam_id', $examId)
                ->with(['student.user', 'userAnswers.question.category'])
                ->get();

            // Pre-calculate total questions per category for this exam
            $examCategoriesTotal = \App\Models\QuestionExam::where('exam_id', $examId)
                ->join('questions', 'question_exam.question_id', '=', 'questions.id')
                ->join('question_categories', 'questions.category_id', '=', 'question_categories.id')
                ->select('question_categories.code', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                ->groupBy('question_categories.code')
                ->pluck('total', 'code')
                ->toArray();

            $monitoringData = [];

            foreach ($activeExams as $userExam) {
                // Determine status based on user_exam status
                $status = 'offline';
                if ($userExam->status === \App\Models\UserExam::STATUS_COMPLETED) {
                    $status = 'finished';
                } else if ($userExam->status === \App\Models\UserExam::STATUS_IN_PROGRESS) {
                    $status = 'online'; // Default to online for in_progress, socket will update to offline if heartbeat missed
                }

                // Calculate time spent
                $timeSpent = 0;
                if ($userExam->start_time) {
                    $endTime = $userExam->end_time ? \Carbon\Carbon::parse($userExam->end_time) : now();
                    $timeSpent = \Carbon\Carbon::parse($userExam->start_time)->diffInSeconds($endTime);
                }

                // Initialize categories
                $categories = [
                    'TWK' => ['score' => 0, 'answered' => 0, 'total' => $examCategoriesTotal['TWK'] ?? $examCategoriesTotal['twk'] ?? 30],
                    'TIU' => ['score' => 0, 'answered' => 0, 'total' => $examCategoriesTotal['TIU'] ?? $examCategoriesTotal['tiu'] ?? 35],
                    'TKP' => ['score' => 0, 'answered' => 0, 'total' => $examCategoriesTotal['TKP'] ?? $examCategoriesTotal['tkp'] ?? 45]
                ];

                // Process answers
                foreach ($userExam->userAnswers as $answer) {
                    if (!$answer->question || !$answer->question->category) continue;

                    $catCode = strtoupper($answer->question->category->code);

                    // Only process TWK, TIU, TKP
                    if (isset($categories[$catCode])) {
                        $categories[$catCode]['score'] += (int) $answer->score;
                        $categories[$catCode]['answered'] += 1;
                    }
                }

                $monitoringData[] = [
                    'id' => $userExam->student_id, // frontend expects student uuid as id
                    'name' => $userExam->student->user->name ?? 'Unknown',
                    'status' => $status,
                    'timeRemaining' => $timeSpent, // Based on FE's note: time_spent replaced by timeRemaining
                    'totalScore' => (int) $userExam->current_score,
                    'categories' => (object) $categories
                ];
            }

            return $this->successResponse($monitoringData, 'Data monitoring realtime berhasil diambil');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('getRealtimeMonitoring error: ' . $e->getMessage());
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
        $this->socketIOService->broadcastExamMonitoring($monitoring);

        if ($monitoring->action === ExamMonitoring::ACTION_ANSWER) {
            $categoryResult = $this->getMonitoringByCategory((string) $monitoring->exam_id);
            if ($categoryResult['success'] ?? false) {
                $categories = $categoryResult['data']['categories'] ?? [];
                $this->socketIOService->broadcastCategoryMonitoring((string) $monitoring->exam_id, $categories);
            }

            $cheating = $this->detectCheating($monitoring);
            if ($cheating) {
                $this->socketIOService->broadcastCheatingDetected($monitoring, $cheating);
            }
        }
    }

    private function detectCheating(ExamMonitoring $monitoring): ?array
    {
        if ($monitoring->action !== ExamMonitoring::ACTION_ANSWER) {
            return null;
        }

        $fastAnswers = ExamMonitoring::where('user_exam_id', $monitoring->user_exam_id)
            ->where('action', ExamMonitoring::ACTION_ANSWER)
            ->where('created_at', '>=', Carbon::now()->subMinute())
            ->where('time_spent', '<=', 2)
            ->count();

        if ($fastAnswers >= 5) {
            return [
                'rule' => 'fast_answers',
                'count' => $fastAnswers,
                'window_seconds' => 60,
                'max_time_spent' => 2,
            ];
        }

        return null;
    }
}
