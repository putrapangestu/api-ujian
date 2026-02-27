<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamRequest;
use App\Models\Exam;
use App\Services\ExamService;
use App\Services\ExamQuestionSelectorService;
use App\Services\QuestionService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Exam Controller
 *
 * Controller ini menangani semua operasi terkait ujian
 *
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class ExamController extends Controller
{
    /**
     * @var ExamService
     */
    protected ExamService $examService;

    /**
     * @var ExamQuestionSelectorService
     */
    protected ExamQuestionSelectorService $questionSelectorService;
    protected QuestionService $questionService;

    /**
     * ExamController constructor
     *
     * @param ExamService $examService
     * @param ExamQuestionSelectorService $questionSelectorService
     */
    public function __construct(
        ExamService $examService,
        ExamQuestionSelectorService $questionSelectorService,
        QuestionService $questionService
    )
    {
        $this->examService = $examService;
        $this->questionSelectorService = $questionSelectorService;
        $this->questionService = $questionService;
    }

    /**
     * Get all exams
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exams",
        summary: "Daftar ujian",
        tags: ["Exams"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
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
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exams/{id}",
        summary: "Detail ujian",
        tags: ["Exams"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function show($id)
    {
        $result = $this->examService->getExamById($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Create new exam
     *
     * @param ExamRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/admin/exams",
        summary: "Buat ujian",
        tags: ["Admin","Exams"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "title", type: "string", maxLength: 255),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "duration", type: "integer", minimum: 1, maximum: 300),
                    new OA\Property(property: "total_questions", type: "integer", minimum: 1, maximum: 200),
                    new OA\Property(property: "passing_score", type: "integer", minimum: 0, maximum: 100),
                    new OA\Property(property: "start_date", type: "string", format: "date-time"),
                    new OA\Property(property: "end_date", type: "string", format: "date-time"),
                    new OA\Property(property: "question_groups", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "student_groups", type: "array", items: new OA\Items(type: "string")),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
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
     * @param ExamRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Put(
        path: "/admin/exams/{id}",
        summary: "Perbarui ujian",
        tags: ["Admin","Exams"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "title", type: "string", maxLength: 255),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "duration", type: "integer", minimum: 1, maximum: 300),
                    new OA\Property(property: "total_questions", type: "integer", minimum: 1, maximum: 200),
                    new OA\Property(property: "passing_score", type: "integer", minimum: 0, maximum: 100),
                    new OA\Property(property: "start_date", type: "string", format: "date-time"),
                    new OA\Property(property: "end_date", type: "string", format: "date-time"),
                    new OA\Property(property: "question_groups", type: "array", items: new OA\Items(type: "integer")),
                    new OA\Property(property: "student_groups", type: "array", items: new OA\Items(type: "string")),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
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
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Delete(
        path: "/admin/exams/{id}",
        summary: "Hapus ujian",
        tags: ["Admin","Exams"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
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
     * Select questions from question banks for exam
     *
     * @param \Illuminate\Http\Request $request
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/exams/{id}/select-questions",
        summary: "Pilih soal dari bank untuk ujian",
        tags: ["Exams","Exam Questions"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "question_banks", type: "array", items: new OA\Items(type: "string"))
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    #[OA\Post(
        path: "/exam-questions/{exam}/select-from-banks",
        summary: "Pilih soal dari bank untuk ujian",
        tags: ["Exams","Exam Questions"],
        parameters: [
            new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "question_banks", type: "array", items: new OA\Items(type: "string"))
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
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
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/exams/validate-question-banks",
        summary: "Validasi pilihan bank soal",
        tags: ["Exams","Exam Questions"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "question_banks", type: "array", items: new OA\Items(type: "object"))
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    #[OA\Post(
        path: "/exam-questions/validate-banks",
        summary: "Validasi pilihan bank soal",
        tags: ["Exams","Exam Questions"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "question_banks", type: "array", items: new OA\Items(type: "object"))
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
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
     * @param \Illuminate\Http\Request $request
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/exams/{id}/questions-for-student",
        summary: "Ambil soal ujian untuk siswa",
        tags: ["Exams","Exam Questions"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "student_id", type: "integer"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    #[OA\Post(
        path: "/exam-questions/{examId}/for-student",
        summary: "Ambil soal ujian untuk siswa",
        tags: ["Exams","Exam Questions"],
        parameters: [
            new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "student_id", type: "integer"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getExamQuestionsForStudent(Request $request, $examId)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
        ]);

        $result = $this->questionSelectorService->getExamQuestionsForStudent($examId, $request->student_id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get exam questions with filters
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exams/{id}/questions",
        summary: "Daftar soal ujian",
        tags: ["Exams","Exam Questions"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "question_group_id", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "question_category_id", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "question_type", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
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
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exams/{id}/user-exams",
        summary: "Daftar user exam berdasarkan ujian",
        tags: ["Exams"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "student_id", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "user_id", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getUserExamsByExam(Request $request, $id)
    {
        $filters = $request->only(['status', 'student_id', 'user_id']);
        $perPage = (int) $request->get('per_page', 15);
        $result = $this->examService->getUserExamsByExam($id, $filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Start exam for authenticated user
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/exams/{id}/start",
        summary: "Mulai ujian untuk user terautentikasi",
        tags: ["Exams"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
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
}
