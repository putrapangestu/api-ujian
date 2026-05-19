<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Student;
use App\Models\UserExam;
use App\Models\UserAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\ExamMonitoring;
use App\Repositories\ExamRepositoryInterface;
use App\Helpers\ResponseHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            $query = Exam::query()
                ->withCount('questionExams');

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

            $examIds = $exams->pluck('id');

            $groupsByExam = DB::table('user_exams')
                ->whereIn('user_exams.exam_id', $examIds)
                ->join('users', 'users.id', '=', 'user_exams.user_id')
                ->join('students', 'students.user_id', '=', 'users.id')
                ->join('student_groups', 'student_groups.id', '=', 'students.group_id')
                ->select('user_exams.exam_id', 'student_groups.id', 'student_groups.name', 'student_groups.description')
                ->distinct()
                ->get()
                ->groupBy('exam_id');

            $exams->getCollection()->transform(function ($exam) use ($groupsByExam) {
                $groups = $groupsByExam->get($exam->id, collect());
                $exam->setRelation('studentGroups', $groups);
                return $exam;
            });

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
            $exam = $this->examRepository->find($id, ['questions', 'questionGroups']);

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
                if (!isset($data["total_questions"]))
                    $data["total_questions"] = 0;

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

                return $this->successResponse($exam->loadMissing(['questionGroups']), 'Ujian berhasil dibuat', 201);

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
                if (!isset($data["total_questions"]))
                    $data["total_questions"] = $exam->total_questions;

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
                    ->where('status', UserExam::STATUS_PENDING)
                    ->first();

                if ($userExam) {
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
            if (!$exam) {
                return $this->errorResponse('Ujian tidak ditemukan', null, 404);
            }

            $query = \App\Models\UserExam::where('exam_id', $examId)
                ->with(['user.student.group', 'userAnswers.question.category']);

            if (isset($filters['status']) && $filters['status']) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['student_id']) && $filters['student_id']) {
                $query->where('student_id', $filters['student_id']);
            }

            if (isset($filters['user_id']) && $filters['user_id']) {
                $query->where('user_id', $filters['user_id']);
            }

            if (isset($filters['search']) && $filters['search']) {
                $query->whereRelation('user','name', 'like', '%' . $filters['search'] . '%');
            }

            $userExams = $query->orderByDesc('created_at')->paginate($perPage);

            // Mapping data to include detailed scores and user info
            $mappedData = [];
            foreach ($userExams as $userExam) {
                $scoreTwk = 0;
                $scoreTiu = 0;
                $scoreTkp = 0;

                foreach ($userExam->userAnswers as $answer) {
                    $categoryCode = strtolower($answer->question?->category?->code ?? '');

                    if (str_contains($categoryCode, 'twk')) {
                        $scoreTwk += $answer->score;
                    } elseif (str_contains($categoryCode, 'tiu')) {
                        $scoreTiu += $answer->score;
                    } elseif (str_contains($categoryCode, 'tkp')) {
                        $scoreTkp += $answer->score;
                    }
                }

                $student = $userExam->user?->student;

                $mappedData[] = [
                    'id' => $userExam->id,
                    'user_id' => $userExam->user_id,
                    'student_id' => $userExam->student_id,
                    'exam_id' => $userExam->exam_id,
                    'start_time' => $userExam->start_time,
                    'end_time' => $userExam->end_time,
                    'status' => $userExam->status,
                    'score' => $userExam->score,
                    'total_score' => $userExam->score ?? 0,
                    'score_twk' => $scoreTwk,
                    'score_tiu' => $scoreTiu,
                    'score_tkp' => $scoreTkp,
                    'user' => [
                        'name' => $userExam->user?->name,
                        'email' => $userExam->user?->email,
                        'nisn' => $student?->nisn,
                        'address' => $student?->address,
                        'group_name' => $student?->group?->name,
                    ],
                    'is_passed' => $userExam->score >= ($exam->passing_grade ?? 0),
                ];
            }

            return [
                'success' => true,
                'message' => 'Daftar user exam berhasil diambil',
                'data' => \App\Helpers\ResponseHelper::formatDateTimes($mappedData),
                'errors' => null,
                'pagination' => [
                    'current_page' => $userExams->currentPage(),
                    'total_pages' => $userExams->lastPage(),
                    'per_page' => $userExams->perPage(),
                    'total_items' => $userExams->total(),
                    'has_next_page' => $userExams->hasMorePages(),
                    'has_previous_page' => $userExams->currentPage() > 1
                ],
                'code' => 200
            ];
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar user exam: ' . $e->getMessage());
        }
    }

    /**
     * Export user exams to Excel
     *
     * @param string $examId
     * @param array $filters
     * @return array
     */
    public function exportUserExams(string $examId, array $filters = []): array
    {
        try {
            $exam = $this->examRepository->find($examId);
            if (!$exam) {
                return $this->errorResponse('Ujian tidak ditemukan', null, 404);
            }

            $query = \App\Models\UserExam::where('exam_id', $examId)
                ->with(['user.student.group', 'userAnswers.question.category']);

            if (isset($filters['status']) && $filters['status']) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['student_id']) && $filters['student_id']) {
                $query->where('student_id', $filters['student_id']);
            }

            if (isset($filters['user_id']) && $filters['user_id']) {
                $query->where('user_id', $filters['user_id']);
            }

            if (isset($filters['search']) && $filters['search']) {
                $search = $filters['search'];
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($sq) use ($search) {
                            $sq->where('nisn', 'like', "%{$search}%")
                                ->orWhereHas('group', function ($gq) use ($search) {
                                    $gq->where('name', 'like', "%{$search}%");
                                });
                        });
                });
            }

            // Batasi jumlah row yang diekspor agar tidak OOM
            $userExams = $query->orderByDesc('created_at')->limit(5000)->get();

            if ($userExams->isEmpty()) {
                return $this->errorResponse('Tidak ada data peserta ujian untuk diekspor', null, 404);
            }

            // Create Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Data Peserta Ujian');

            // Set headers
            $headers = [
                'A1' => 'Nama Peserta',
                'B1' => 'Sekolah',
                'C1' => 'Grup Siswa',
                'D1' => 'Waktu Mulai',
                'E1' => 'Waktu Selesai',
                'F1' => 'Status',
                'G1' => 'Skor TWK',
                'H1' => 'Skor TIU',
                'I1' => 'Skor TKP',
                'J1' => 'Total Skor',
                'K1' => 'Status Kelulusan'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
            }

            $row = 2;
            foreach ($userExams as $userExam) {
                $scoreTwk = 0;
                $scoreTiu = 0;
                $scoreTkp = 0;

                foreach ($userExam->userAnswers as $answer) {
                    $categoryCode = strtolower($answer->question?->category?->code ?? '');

                    if (str_contains($categoryCode, 'twk')) {
                        $scoreTwk += $answer->score;
                    } elseif (str_contains($categoryCode, 'tiu')) {
                        $scoreTiu += $answer->score;
                    } elseif (str_contains($categoryCode, 'tkp')) {
                        $scoreTkp += $answer->score;
                    }
                }

                $student = $userExam->user?->student;
                $isPassed = $userExam->score >= ($exam->passing_grade ?? 0) ? 'Lulus' : 'Tidak Lulus';

                $sheet->setCellValue('A' . $row, $userExam->user?->name);
                // $sheet->setCellValue('B' . $row, $userExam->user?->email);
                $sheet->setCellValueExplicit('B' . $row, $student?->address, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('C' . $row, $student?->group?->name);
                $sheet->setCellValue('D' . $row, $userExam->start_time ? date('Y-m-d H:i:s', strtotime($userExam->start_time)) : '-');
                $sheet->setCellValue('E' . $row, $userExam->end_time ? date('Y-m-d H:i:s', strtotime($userExam->end_time)) : '-');
                $sheet->setCellValue('F' . $row, ucfirst($userExam->status));
                $sheet->setCellValue('G' . $row, $scoreTwk);
                $sheet->setCellValue('H' . $row, $scoreTiu);
                $sheet->setCellValue('I' . $row, $scoreTkp);
                $sheet->setCellValue('J' . $row, $userExam->score ?? 0);
                $sheet->setCellValue('K' . $row, $isPassed);

                // Highlight kelulusan
                if ($userExam->score >= ($exam->passing_grade ?? 0)) {
                    $sheet->getStyle('K' . $row)->getFont()->getColor()->setARGB('FF008000'); // Green
                } else {
                    $sheet->getStyle('K' . $row)->getFont()->getColor()->setARGB('FFFF0000'); // Red
                }

                $row++;
            }

            // Auto size columns
            foreach (range('A', 'K') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create file
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $exam->title);
            $filename = 'Hasil_Ujian_' . $safeName . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filePath = 'exports/' . $filename;
            $fullPath = storage_path('app/public/' . $filePath);

            // Ensure directory exists
            if (!file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($fullPath);

            return $this->successResponse([
                'file_path' => $filePath,
                'file_name' => $filename,
                'download_url' => asset('storage/' . $filePath)
            ], 'Export data peserta ujian berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal export data peserta ujian: ' . $e->getMessage());
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
                        $query->with([
                            'question' => function ($q) {
                                $q->withTrashed();
                            },
                            'questionOption' => function ($q) {
                                $q->withTrashed();
                            }
                        ])
                        ->orderBy('created_at','asc');
                    },
                ])
                ->first();

            if (!$userExam) {
                return $this->errorResponse('Riwayat ujian tidak ditemukan', null, 404);
            }

            $answers = $userExam->userAnswers;
            $answeredCount = $answers->filter(function ($answer) {
                return $answer->answer !== null || $answer->question_option_id !== null;
            })->count();

            // Handle multiple-choice options yang tersimpan sebagai JSON array di kolom answer
            $multipleChoiceOptionIds = [];
            foreach ($answers as $answer) {
                if (empty($answer->question_option_id) && is_string($answer->answer) && str_starts_with(trim($answer->answer), '[')) {
                    $decoded = json_decode($answer->answer, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $multipleChoiceOptionIds = array_merge($multipleChoiceOptionIds, $decoded);
                    }
                }
            }

            if (!empty($multipleChoiceOptionIds)) {
                $multipleOptions = QuestionOption::withTrashed()
                    ->whereIn('id', array_unique($multipleChoiceOptionIds))
                    ->get()
                    ->keyBy('id');

                foreach ($answers as $answer) {
                    if (empty($answer->question_option_id) && is_string($answer->answer) && str_starts_with(trim($answer->answer), '[')) {
                        $decoded = json_decode($answer->answer, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $selectedOpts = collect();
                            foreach ($decoded as $oid) {
                                if ($multipleOptions->has($oid)) {
                                    $selectedOpts->push($multipleOptions->get($oid));
                                }
                            }
                            $answer->setAttribute('selected_options', $selectedOpts);
                            $answer->setAttribute('decoded_answer', $decoded);
                        }
                    }
                }
            }

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
                ->whereIn('status', [UserExam::STATUS_PENDING, UserExam::STATUS_IN_PROGRESS])
                ->with(['exam'])
                ->get()
                ->sortBy(function ($userExam) {
                    return $userExam->exam?->start_date ?? $userExam->exam?->created_at ?? $userExam->created_at;
                })
                ->values();

            $todaySummary = $todayUserExams
                ->groupBy('status')
                ->map(fn($items) => $items->count());

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

    /**
     * Finish exam for user with optional grading
     *
     * @param string $examId
     * @param string $userId
     * @param array $questions Optional answers payload
     * @return array
     */
    public function finishExam(string $examId, string $userId, array $questions = []): array
    {
        Log::info("Jawaban siswa", [$questions]);
        return DB::transaction(function () use ($examId, $userId, $questions) {
            try {
                $exam = $this->examRepository->find($examId);
                if (!$exam) {
                    return $this->errorResponse('Ujian tidak ditemukan', null, 404);
                }

                $userExam = UserExam::where('user_id', $userId)
                    ->where('exam_id', $examId)
                    ->first();

                if (!$userExam) {
                    return $this->errorResponse('Ujian untuk user ini tidak ditemukan atau belum dimulai', null, 404);
                }

                if ($userExam->isCompleted()) {
                    return $this->errorResponse('Ujian sudah diselesaikan sebelumnya');
                }

                if (!$userExam->isInProgress()) {
                    return $this->errorResponse('Ujian belum dimulai', null, 400);
                }

                $processed = 0;
                $invalidQuestions = [];

                if (!empty($questions) && is_array($questions)) {
                    // Preprocess questions untuk mengatasi problem stringified JSON array dari FE
                    $questions = array_map(function ($item) {
                        if (isset($item['question_option_id']) && is_string($item['question_option_id']) && str_starts_with(trim($item['question_option_id']), '[')) {
                            $decoded = json_decode($item['question_option_id'], true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $item['question_option_id'] = $decoded;
                            }
                        }
                        return $item;
                    }, $questions);

                    $items = collect($questions);
                    $questionIds = $items->pluck('question_id')->filter()->unique()->values();
                    // Gunakan flatten() agar menangkap list string jika question_option_id dikirim sebagai array (multiple choice)
                    $optionIds = $items->pluck('question_option_id')->flatten()->filter()->unique()->values();
                    Log::info("questionIds => ", [$questionIds]);
                    Log::info("optionIds => ", [$optionIds]);
                    Log::info("question from exam", [$exam->questions()->get()->pluck('id')->toArray()]);

                    $examQuestions = Question::whereIn('id', $questionIds->toArray())
                        ->where('exam_id', $exam->id)
                        ->get()
                        ->keyBy('id');
                    $options = $optionIds->isNotEmpty()
                        ? QuestionOption::whereIn('id', $optionIds->toArray())->get()->keyBy('id')
                        : collect();
                    $monitoringRows = [];
                    $now = Carbon::now();

                    Log::info("check item => ", [$items]);
                    Log::info("exam questions", [$examQuestions]);
                    Log::info("pilihan dari database", [$options]);
                    foreach ($items as $item) {
                        $questionId = $item['question_id'] ?? null;
                        if (!$questionId) {
                            continue;
                        }

                        $question = $examQuestions->get($questionId);
                        if (!$question) {
                            $invalidQuestions[] = $questionId;
                            continue;
                        }

                        $answerText = $item['answer'] ?? null;
                        $questionOptionIdInput = $item['question_option_id'] ?? null;
                        $timeSpent = $item['time_spent'] ?? null;
                        $questionOrder = $item['question_order'] ?? null;
                        $questionNumber = $item['question_number'] ?? null;

                        $isCorrect = false;
                        $score = 0;
                        $questionOptionIdToSave = null;

                        if ($questionOptionIdInput) {
                            if (is_array($questionOptionIdInput)) {
                                $selectedOptions = collect();
                                foreach ($questionOptionIdInput as $oid) {
                                    $opt = $options->get($oid);
                                    if ($opt && $opt->question_id === $questionId) {
                                        $selectedOptions->push($opt);
                                    }
                                }

                                if ($selectedOptions->isNotEmpty()) {
                                    // Asumsi skor multiple_choice = penjumlahan skor jawaban yang dipilih
                                    $score = $selectedOptions->sum('score');
                                    $isCorrect = $score > 0;
                                    // Simpan array UUID jawaban ganda ke kolom answer
                                    $answerText = json_encode($questionOptionIdInput);
                                }
                                // Kosongkan foreign key karena kita menyimpannya di kolom answer
                                $questionOptionIdToSave = null;
                            } else {
                                $option = $options->get($questionOptionIdInput);
                                if ($option && $option->question_id === $questionId) {
                                    $score = (int) $option->score;
                                    $isCorrect = $score > 0;
                                    $answerText = $answerText ?? $option->option_text;
                                }
                                $questionOptionIdToSave = $questionOptionIdInput;
                            }
                        } elseif ($answerText !== null) {
                            $isCorrect = $question->isCorrect($answerText);
                            $score = $isCorrect ? (int) $question->score : 0;
                        } else {
                            continue;
                        }

                        $finalAnswerText = is_array($answerText) ? json_encode($answerText) : $answerText;

                        $payload = [
                            'question_option_id' => $questionOptionIdToSave,
                            'answer' => $finalAnswerText,
                            'is_correct' => $isCorrect,
                            'score' => $score,
                        ];
                        if ($timeSpent !== null) {
                            $payload['time_spent'] = $timeSpent;
                        }
                        if ($questionOrder !== null) {
                            $payload['question_order'] = $questionOrder;
                        }
                        if ($questionNumber !== null) {
                            $payload['question_number'] = $questionNumber;
                        }

                        Log::info("Payload update data student exam finish => ", [$payload]);
                        UserAnswer::updateOrCreate(
                            [
                                'user_exam_id' => $userExam->id,
                                'question_id' => $questionId,
                            ],
                            $payload
                        );

                        $monitoringRows[] = [
                            'id' => (string) Str::uuid(),
                            'user_exam_id' => $userExam->id,
                            'student_id' => $userExam->student_id,
                            'exam_id' => $userExam->exam_id,
                            'action' => ExamMonitoring::ACTION_ANSWER,
                            'question_id' => $questionId,
                            'answer' => $finalAnswerText,
                            'time_spent' => $timeSpent ?? 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $processed++;
                    }

                    if (!empty($monitoringRows)) {
                        ExamMonitoring::insert($monitoringRows);
                    }
                }

                $totalScore = (int) $userExam->userAnswers()->sum('score');

                $userExam->update([
                    'end_time' => Carbon::now(),
                    'score' => $totalScore,
                    'status' => UserExam::STATUS_COMPLETED,
                ]);

                ExamMonitoring::create([
                    'user_exam_id' => $userExam->id,
                    'student_id' => $userExam->student_id,
                    'exam_id' => $userExam->exam_id,
                    'action' => ExamMonitoring::ACTION_SUBMIT,
                    'time_spent' => 0,
                ]);

                $answers = $userExam->userAnswers()->with(['question', 'questionOption'])->get();
                $answeredCount = $answers->filter(function ($answer) {
                    return $answer->answer !== null || $answer->question_option_id !== null;
                })->count();

                return $this->successResponse([
                    'user_exam' => $userExam->fresh(),
                    'summary' => [
                        'processed_answers' => $processed,
                        'invalid_question_ids' => $invalidQuestions,
                        'total_questions' => $answers->count(),
                        'answered_questions' => $answeredCount,
                        'correct_answers' => $answers->where('is_correct', true)->count(),
                        'total_score' => (int) $answers->sum('score'),
                    ],
                ], 'Ujian berhasil diselesaikan');
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menyelesaikan ujian: ' . $e->getMessage());
            }
        });
    }

    private function createUserExamsForGroups(Exam $exam, array $groupIds): void
    {
        $students = Student::with('user')
            ->whereIn('group_id', $groupIds)
            ->where('status', Student::STATUS_ACTIVE)
            ->whereHas('user')
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
                    'student_id' => $student->id,
                    'status' => UserExam::STATUS_PENDING,
                    'score' => 0,
                    'start_time' => null,
                    'end_time' => null,
                ]
            );
        }
    }
}
