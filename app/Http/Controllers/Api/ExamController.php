<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamRequest;
use App\Models\Exam;
use App\Models\UserAnswer;
use App\Services\ExamQuestionSelectorService;
use App\Services\ExamService;
use App\Services\QuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionExam;
use App\Models\UserExam;
use Illuminate\Support\Facades\Log;

/**
 * Exam Controller
 *
 * Controller ini menangani semua operasi terkait ujian
 *
 *
 * @author Development Team
 *
 * @version 1.0.0
 */
class ExamController extends Controller
{
    protected ExamService $examService;

    protected ExamQuestionSelectorService $questionSelectorService;

    protected QuestionService $questionService;

    /**
     * ExamController constructor
     */
    public function __construct(
        ExamService $examService,
        ExamQuestionSelectorService $questionSelectorService,
        QuestionService $questionService
    ) {
        $this->examService = $examService;
        $this->questionSelectorService = $questionSelectorService;
        $this->questionService = $questionService;
    }

    /**
     * Get all exams
     *
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/exams',
        summary: 'Daftar ujian',
        tags: ['Exams'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'exam_type']);
        $perPage = (int) $request->get('per_page', 15);
        $result = $this->examService->getAllExams($filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Get exam by ID
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/exams/{id}',
        summary: 'Detail ujian',
        tags: ['Exams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function show($id)
    {
        $result = $this->examService->getExamById($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    #[OA\Get(
        path: '/exams/history',
        summary: 'Riwayat ujian siswa',
        tags: ['Exams'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'completed', 'expired'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function history(Request $request)
    {
        $filters = $request->only(['status', 'search', 'start_date', 'end_date', 'exam_type']);
        $perPage = (int) $request->get('per_page', 15);
        $userId = $request->user()->id;

        $result = $this->examService->getExamHistoryForUser($userId, $filters, $perPage);
        $status = $result['code'] ?? ($result['success'] ? 200 : 400);

        return response()->json($result, $status);
    }

    #[OA\Get(
        path: '/exams/history/{userExamId}',
        summary: 'Detail riwayat ujian siswa',
        tags: ['Exams'],
        parameters: [
            new OA\Parameter(name: 'userExamId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function historyDetail(Request $request, $userExamId)
    {
        $userId = $request->user()->id;
        $result = $this->examService->getExamHistoryDetailForUser($userId, $userExamId);
        $status = $result['code'] ?? ($result['success'] ? 200 : 400);

        return response()->json($result, $status);
    }

    #[OA\Get(
        path: '/exams/today',
        summary: 'Ujian hari ini siswa dan ringkasan',
        tags: ['Exams'],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function today(Request $request)
    {
        $userId = $request->user()->id;
        $result = $this->examService->getTodayExamsForUser($userId);
        $status = $result['code'] ?? ($result['success'] ? 200 : 400);

        return response()->json($result, $status);
    }

    /**
     * Create new exam
     *
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: '/admin/exams',
        summary: 'Buat ujian',
        tags: ['Admin', 'Exams'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'duration', type: 'integer', minimum: 1),
                    new OA\Property(property: 'total_questions', type: 'integer', minimum: 1),
                    new OA\Property(property: 'passing_score', type: 'integer', minimum: 0),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'question_groups', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'student_groups', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function store(ExamRequest $request)
    {
        $result = $this->examService->createExam($request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Update exam
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Put(
        path: '/admin/exams/{id}',
        summary: 'Perbarui ujian',
        tags: ['Admin', 'Exams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'duration', type: 'integer', minimum: 1, maximum: 300),
                    new OA\Property(property: 'total_questions', type: 'integer', minimum: 1, maximum: 200),
                    new OA\Property(property: 'passing_score', type: 'integer', minimum: 0, maximum: 100),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'question_groups', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'student_groups', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function update(ExamRequest $request, $id)
    {
        $result = $this->examService->updateExam($id, $request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Delete exam
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Delete(
        path: '/admin/exams/{id}',
        summary: 'Hapus ujian',
        tags: ['Admin', 'Exams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function destroy($id)
    {
        $result = $this->examService->deleteExam($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Copy/duplicate an exam with its question groups and question exams
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function copyExam(string $id): JsonResponse
    {
        $result = $this->examService->copyExam($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], 201);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Delete user exam
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUserExam(string $id): JsonResponse
    {
        $result = $this->examService->deleteUserExam($id);
        return response()->json($result, $result['code']);
    }

    /**
     * Bulk delete user exams
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUserExamsBulk(Request $request): JsonResponse
    {
        $request->validate([
            'user_exam_ids' => 'required|array|min:1',
            'user_exam_ids.*' => 'required|string|exists:user_exams,id',
        ]);

        $result = $this->examService->deleteMultipleUserExams($request->user_exam_ids);
        return response()->json($result, $result['code']);
    }

    /**
     * Select questions from question banks for exam
     *
     * @param  string  $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: '/exams/{id}/select-questions',
        summary: 'Pilih soal dari bank untuk ujian',
        tags: ['Exams', 'Exam Questions'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'question_banks', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    #[OA\Post(
        path: '/exam-questions/{exam}/select-from-banks',
        summary: 'Pilih soal dari bank untuk ujian',
        tags: ['Exams', 'Exam Questions'],
        parameters: [
            new OA\Parameter(name: 'examId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'question_banks', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function selectQuestionsFromBanks(Request $request, Exam $exam)
    {
        $request->validate([
            'question_banks' => 'required|array|min:1',
            'question_banks.*' => 'required|exists:questions,id',
        ]);

        $result = $this->questionSelectorService->selectQuestionsForExam($exam, $request->question_banks);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Validate question bank selection
     *
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: '/exams/validate-question-banks',
        summary: 'Validasi pilihan bank soal',
        tags: ['Exams', 'Exam Questions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'question_banks', type: 'array', items: new OA\Items(type: 'object')),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    #[OA\Post(
        path: '/exam-questions/validate-banks',
        summary: 'Validasi pilihan bank soal',
        tags: ['Exams', 'Exam Questions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'question_banks', type: 'array', items: new OA\Items(type: 'object')),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function validateQuestionBankSelection(Request $request)
    {
        $request->validate([
            'question_banks' => 'required|array|min:1',
            'question_banks.*.question_group_id' => 'required|integer|exists:question_groups,id',
            'question_banks.*.question_count' => 'required|integer|min:1',
            'question_banks.*.start_range' => 'nullable|integer|min:1',
            'question_banks.*.end_range' => 'nullable|integer|min:1|gte:question_banks.*.start_range',
        ]);

        $result = $this->questionSelectorService->validateQuestionBankSelection($request->question_banks);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get exam questions for student with random order
     *
     * @param  string  $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/exams/{id}/questions-for-student/{userId}',
        summary: 'Ambil soal ujian untuk siswa',
        tags: ['Exams', 'Exam Questions'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function getExamQuestionsForStudent(Request $request, $examId, $userId)
    {
        $exam = Exam::where('id', $examId)->first();
        $userExam = UserExam::where('exam_id', $examId)
            ->with('user.student.group')
            ->where('user_id', $userId)
            ->first();

        if (!$exam || !$userExam) {
            return $this->errorResponse('Ujian atau peserta tidak ditemukan', 404);
        }

        // Cek apakah soal spesifik untuk user ini sudah pernah di-generate
        $hasQuestions = UserAnswer::where('user_exam_id', $userExam->id)->exists();
        // Log::info("Questions", [$hasQuestions]);

        if (!$hasQuestions) {
            // Generate soal sesuai aturan kategori dan rentang untuk siswa ini
            $questions = $this->questionSelectorService->generateExamQuestions($exam);

            $userExamQuestions = [];
            // Log::info("Jumlah questions", [count($questions)]);
            $now = now();
            foreach ($questions as $index => $question) {
                $userExamQuestions[] = [
                    'id'           => (string) \Illuminate\Support\Str::uuid(),
                    'user_exam_id' => $userExam->id,
                    'question_id'  => $question->id,
                    'created_at'   => $now->copy()->addSeconds($index),
                    'updated_at'   => $now->copy()->addSeconds($index),
                ];
            }

            if (!empty($userExamQuestions)) {
                UserAnswer::insert($userExamQuestions);
            }
        }

        // Ambil data soal yang sudah tersimpan di UserAnswer beserta relasinya
        $userAnswers = UserAnswer::with(['question.options', 'question.questionGroup', 'question.category'])
            ->where('user_exam_id', $userExam->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $formattedQuestions = $userAnswers->map(function ($userAnswer, $index) {
            $question = $userAnswer->question;
            return [
                'id'                 => $userAnswer->id,
                'question_id'        => $question?->id,
                'question_number'    => $index + 1,
                'question_order'     => $index + 1,
                'question_text'      => $question?->question_text,
                'question_type'      => $question?->question_type,
                'score'              => $question?->score,
                'category_name'      => $question?->category?->name,
                'group_name'         => $question?->questionGroup?->name,
                'options'            => $question?->options->map(function ($option) {
                    return [
                        'id'          => $option->id,
                        'option_text' => $option->option_text
                    ];
                }),
                'answer'             => $userAnswer->answer,
                'question_option_id' => $userAnswer->question_option_id,
                'is_correct'         => $userAnswer->is_correct,
            ];
        });

        return $this->successResponse([
            'exam'            => $exam,
            'user_exam'       => $userExam,
            'questions'       => $formattedQuestions,
            'total_questions' => $formattedQuestions->count(),
        ], 'Soal ujian siswa berhasil diambil');
    }

    /**
     * Get exam questions with filters
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/exams/{id}/questions',
        summary: 'Daftar soal ujian',
        tags: ['Exams', 'Exam Questions'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'question_group_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'question_category_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'question_type', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function getExamQuestions(Request $request, $id)
    {
        $filters = $request->only(['question_group_id', 'question_category_id', 'search', 'question_type']);
        $filters['exam_id'] = $id;
        $perPage = (int) $request->get('per_page', 15);
        $result = $this->questionService->getAllQuestionExams($filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Get user exams by exam
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/exams/{id}/user-exams',
        summary: 'Daftar user exam berdasarkan ujian',
        tags: ['Exams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'student_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function getUserExamsByExam(Request $request, $id)
    {
        $filters = $request->only(['status', 'student_id', 'user_id', 'search']);
        $perPage = (int) $request->get('per_page', 15);
        $result = $this->examService->getUserExamsByExam($id, $filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Export user exams to Excel
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/exams/{id}/user-exams/export',
        summary: 'Export data peserta ujian',
        tags: ['Exams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'student_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function exportUserExams(Request $request, $id)
    {
        $filters = $request->only(['status', 'student_id', 'user_id', 'search']);
        $result = $this->examService->exportUserExams($id, $filters);

        if ($result['success']) {
            return $this->successResponse($result['data'] ?? $result, $result['message'] ?? 'Berhasil export');
        }

        return $this->errorResponse($result['message'], 400);
    }

    /**
     * Start exam for authenticated user
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: '/exams/{id}/start',
        summary: 'Mulai ujian untuk user terautentikasi',
        tags: ['Exams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function startExam(Request $request, $id)
    {
        $userId = $request->user()->id;
        $result = $this->examService->startExam($id, $userId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    #[OA\Post(
        path: '/exams/{id}/finish',
        summary: 'Selesaikan ujian (opsional kirim jawaban untuk dinilai)',
        tags: ['Exams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'questions',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'question_id', type: 'string'),
                                new OA\Property(property: 'answer', type: 'string', nullable: true),
                                new OA\Property(property: 'question_option_id', description: 'ID opsi jawaban (bisa string tunggal atau array string untuk multiple choice)', nullable: true),
                                new OA\Property(property: 'time_spent', type: 'integer', nullable: true),
                                new OA\Property(property: 'question_order', type: 'integer', nullable: true),
                                new OA\Property(property: 'question_number', type: 'integer', nullable: true),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function finishExam(Request $request, $id)
    {
        $validated = $request->validate([
            'questions' => 'nullable|array',
            'questions.*.question_id' => 'required_with:questions|string',
            'questions.*.answer' => 'nullable|string',
            'questions.*.question_option_id' => 'nullable', // Menghapus limitasi string agar bisa menerima array untuk multiple choice
            'questions.*.time_spent' => 'nullable|integer|min:0',
            'questions.*.question_order' => 'nullable|integer|min:1',
            'questions.*.question_number' => 'nullable|integer|min:1',
        ]);

        $userId = $request->user()->id;
        $questions = $validated['questions'] ?? [];

        $result = $this->examService->finishExam($id, $userId, $questions);

        if ($result['success']) {
            // Broadcast ke CMS monitoring room biar real-time update
            try {
                $student = $request->user()->student;
                if ($student) {
                    app(\App\Services\SocketIOService::class)->emitEvent(
                        'exam:' . $id,
                        'exam:progress_update',
                        [
                            'id' => $student->id,
                            'update' => [
                                'status' => 'finished',
                                'totalScore' => $result['data']['summary']['total_score'] ?? 0,
                                'timeRemaining' => 0,
                            ]
                        ],
                        'exam:' . $id
                    );
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to emit finish socket: ' . $e->getMessage());
            }

            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], $result['code'] ?? 400, $result['errors'] ?? null);
    }
}
