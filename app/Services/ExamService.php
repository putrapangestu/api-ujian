<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Student;
use App\Models\UserExam;
use App\Repositories\ExamRepositoryInterface;
use App\Helpers\ResponseHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Exam Service Class
 *
 * Service ini menangani semua operasi terkait ujian
 * Menggunakan database transaction untuk operasi yang melibatkan multiple tables
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class ExamService extends BaseService
{
    /**
     * @var ExamRepositoryInterface
     */
    protected ExamRepositoryInterface $examRepository;

    /**
     * ExamService constructor
     *
     * @param ExamRepositoryInterface $examRepository
     */
    public function __construct(ExamRepositoryInterface $examRepository)
    {
        $this->examRepository = $examRepository;
    }

    /**
     * Get all exams
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllExams(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = Exam::query()->withCount('questions');

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $exams = $query->paginate($perPage);

            return ResponseHelper::paginate($exams, 'Daftar ujian berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar ujian: ' . $e->getMessage());
        }
    }

    /**
     * Get exam by ID
     *
     * @param string $id
     * @return array
     */
    public function getExamById(string $id): array
    {
        try {
            $exam = $this->examRepository->find($id, ['questions','questionGroups']);

            if (!$exam) {
                return $this->errorResponse('Ujian tidak ditemukan', null, 404);
            }

            return $this->successResponse($exam, 'Detail ujian berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil detail ujian: ' . $e->getMessage());
        }
    }

    /**
     * Create new exam
     *
     * @param array $data Validated data from request
     * @return array
     */
    public function createExam(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                // set total question
                if(!isset($data["total_questions"])) $data["total_questions"] = 0;

                // Create exam
                $exam = $this->examRepository->create($data);

                // Attach question groups if provided
                if (isset($data['question_groups']) && is_array($data['question_groups'])) {
                    $exam->questionGroups()->attach($data['question_groups']);
                }

                // Attach student groups if provided
                if (isset($data['student_groups']) && is_array($data['student_groups'])) {
                    $this->createUserExamsForGroups($exam, $data['student_groups']);
                }

                return $this->successResponse($exam->load(['questionGroups']), 'Ujian berhasil dibuat', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal membuat ujian: ' . $e->getMessage());
            }
        });
    }

    /**
     * Update exam
     *
     * @param string $id
     * @param array $data Validated data from request
     * @return array
     */
    public function updateExam(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            try {
                // Check if exam exists
                $exam = $this->examRepository->find($id);
                if (!$exam) {
                    return $this->errorResponse('Ujian tidak ditemukan', null, 404);
                }

                // set total questions
                if(!isset($data["total_questions"])) $data["total_questions"] = $exam->total_questions;

                // Update exam
                $updated = $this->examRepository->update($id, $data);

                if (!$updated) {
                    return $this->errorResponse('Gagal update ujian');
                }

                // Update question groups if provided
                if (isset($data['question_groups']) && is_array($data['question_groups'])) {
                    $exam->questionGroups()->sync($data['question_groups']);
                }

                if (isset($data['student_groups']) && is_array($data['student_groups'])) {
                    $this->createUserExamsForGroups($exam, $data['student_groups']);
                }

                // Get updated exam with relationships
                $updatedExam = $this->examRepository->find($id, ['questionGroups']);

                return $this->successResponse($updatedExam, 'Ujian berhasil diupdate');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal update ujian: ' . $e->getMessage());
            }
        });
    }

    /**
     * Delete exam
     *
     * @param string $id
     * @return array
     */
    public function deleteExam(string $id): array
    {
        return DB::transaction(function () use ($id) {
            try {
                // Check if exam exists
                $exam = $this->examRepository->find($id);
                if (!$exam) {
                    return $this->errorResponse('Ujian tidak ditemukan', null, 404);
                }

                // Delete exam
                $deleted = $this->examRepository->delete($id);

                if (!$deleted) {
                    return $this->errorResponse('Gagal menghapus ujian');
                }

                return $this->successResponse(null, 'Ujian berhasil dihapus');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menghapus ujian: ' . $e->getMessage());
            }
        });
    }

    /**
     * Start exam for user
     *
     * @param string $examId
     * @param string $userId
     * @return array
     */
    public function startExam(string $examId, string $userId): array
    {
        return DB::transaction(function () use ($examId, $userId) {
            try {
                // Check if exam exists
                $exam = $this->examRepository->find($examId);
                if (!$exam) {
                    return $this->errorResponse('Ujian tidak ditemukan', null, 404);
                }

                // Check if exam is available
                if (!$exam->isAvailable()) {
                    return $this->errorResponse('Ujian tidak tersedia saat ini');
                }

                // Check if user already has active exam
                $existingExam = UserExam::where('user_id', $userId)
                    ->where('exam_id', $examId)
                    ->whereIn('status', ['in_progress', 'completed'])
                    ->first();

                if ($existingExam) {
                    return $this->errorResponse('Anda sudah pernah mengikuti ujian ini');
                }

                // Create user exam
                $userExam = UserExam::where('user_id', $userId)
                    ->where('exam_id', $examId)
                    ->first();

                if($userExam->status == UserExam::STATUS_IN_PROGRESS) {
                    return $this->errorResponse('Kamu sudah mengikuti ujian ini');
                } else if($userExam->status == UserExam::STATUS_COMPLETED) {
                    return $this->errorResponse('Kamu sudah selesai mengikuti ujian ini');
                }

                if($userExam) {
                    $userExam->update([
                        'start_time' => Carbon::now(),
                        'status' => UserExam::STATUS_IN_PROGRESS,
                    ]);
                } else {
                    $userExam = UserExam::create([
                        'user_id' => $userId,
                        'exam_id' => $examId,
                        'start_time' => Carbon::now(),
                        'status' => UserExam::STATUS_IN_PROGRESS,
                    ]);
                }

                // set load
                $userExam->load([
                    'questionExams.question.options',
                    'questionExams.questionGroup.questionCategory'
                ]);

                return $this->successResponse([
                    'user_exam' => $userExam,
                    'exam' => $exam,
                    'end_time' => Carbon::now()->addMinutes($exam->duration)
                ], 'Ujian berhasil dimulai');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal memulai ujian: ' . $e->getMessage());
            }
        });
    }

    /**
     * Get user exams by exam ID
     *
     * @param string $examId
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getUserExamsByExam(string $examId, array $filters = [], int $perPage = 15): array
    {
        try {
            $exam = $this->examRepository->find($examId);
            if (! $exam) {
                return $this->errorResponse('Ujian tidak ditemukan', null, 404);
            }

            $query = UserExam::where('exam_id', $examId)
                ->with(['user.student.group']);

            if (isset($filters['status']) && $filters['status']) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['student_id']) && $filters['student_id']) {
                $query->where('student_id', $filters['student_id']);
            }

            if (isset($filters['user_id']) && $filters['user_id']) {
                $query->where('user_id', $filters['user_id']);
            }

            $userExams = $query->orderByDesc('created_at')->paginate($perPage);

            return ResponseHelper::paginate($userExams, 'Daftar user exam berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar user exam: ' . $e->getMessage());
        }
    }

    public function getExamHistoryForUser(string $userId, array $filters = [], int $perPage = 15): array
    {
        try {
            $query = UserExam::where('user_id', $userId)
                ->with(['exam'])
                ->withCount('userAnswers');

            if (isset($filters['status']) && $filters['status']) {
                $query->where('status', $filters['status']);
            }

            $userExams = $query->orderByDesc('created_at')->paginate($perPage);

            return ResponseHelper::paginate($userExams, 'Riwayat ujian berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil riwayat ujian: ' . $e->getMessage());
        }
    }

    public function getExamHistoryDetailForUser(string $userId, string $userExamId): array
    {
        try {
            $userExam = UserExam::where('id', $userExamId)
                ->where('user_id', $userId)
                ->with([
                    'exam',
                    'userAnswers' => function ($query) {
                        $query->with(['question', 'questionOption'])
                            ->orderBy('question_order');
                    },
                ])
                ->first();

            if (! $userExam) {
                return $this->errorResponse('Riwayat ujian tidak ditemukan', null, 404);
            }

            $answers = $userExam->userAnswers;
            $answeredCount = $answers->filter(function ($answer) {
                return $answer->answer !== null || $answer->question_option_id !== null;
            })->count();

            return $this->successResponse([
                'user_exam' => $userExam,
                'summary' => [
                    'total_questions' => $answers->count(),
                    'answered_questions' => $answeredCount,
                    'correct_answers' => $answers->where('is_correct', true)->count(),
                    'total_score' => (int) $answers->sum('score'),
                ],
            ], 'Detail riwayat ujian berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil detail riwayat ujian: ' . $e->getMessage());
        }
    }

    public function getTodayExamsForUser(string $userId): array
    {
        try {
            $startOfDay = Carbon::today();
            $endOfDay = Carbon::today()->endOfDay();

            $todayUserExams = UserExam::where('user_id', $userId)
                ->whereHas('exam', function ($query) use ($startOfDay, $endOfDay) {
                    $query
                        ->where(function ($q) use ($endOfDay) {
                            $q->whereNull('start_date')->orWhere('start_date', '<=', $endOfDay);
                        })
                        ->where(function ($q) use ($startOfDay) {
                            $q->whereNull('end_date')->orWhere('end_date', '>=', $startOfDay);
                        });
                })
                ->with(['exam'])
                ->get()
                ->sortBy(function ($userExam) {
                    return $userExam->exam?->start_date ?? $userExam->exam?->created_at ?? $userExam->created_at;
                })
                ->values();

            $todaySummary = $todayUserExams
                ->groupBy('status')
                ->map(fn ($items) => $items->count());

            $overallSummary = UserExam::where('user_id', $userId)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $averageScore = UserExam::where('user_id', $userId)
                ->where('status', UserExam::STATUS_COMPLETED)
                ->avg('score');

            return $this->successResponse([
                'today' => $todayUserExams,
                'today_summary' => $todaySummary,
                'overall_summary' => [
                    'by_status' => $overallSummary,
                    'average_score' => $averageScore !== null ? round((float) $averageScore, 2) : null,
                ],
                'server_time' => Carbon::now(),
            ], 'Data ujian hari ini berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil ujian hari ini: ' . $e->getMessage());
        }
    }

    private function createUserExamsForGroups(Exam $exam, array $groupIds): void
    {
        $students = Student::whereIn('group_id', $groupIds)
            ->where('status', Student::STATUS_ACTIVE)
            ->whereNotNull('user_id')
            ->get();

        foreach ($students as $student) {
            UserExam::firstOrCreate(
                [
                    'exam_id' => $exam->id,
                    'user_id' => $student->user_id,
                ],
                [
                    'user_id' => $student->user_id,
                    'status' => UserExam::STATUS_PENDING,
                    'score' => 0,
                    'start_time' => null,
                    'end_time' => null,
                ]
            );
        }
    }
}
