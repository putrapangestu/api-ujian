<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamRequest;
use App\Services\ExamService;
use App\Services\ExamQuestionSelectorService;
use Illuminate\Http\Request;

/**
 * Exam Controller
 *
 * Controller ini menangani semua operasi terkait ujian
 * 
 * @OA\Tag(
 *     name="Exams",
 *     description="API endpoints for managing exams"
 * )
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

    /**
     * ExamController constructor
     *
     * @param ExamService $examService
     * @param ExamQuestionSelectorService $questionSelectorService
     */
    public function __construct(ExamService $examService, ExamQuestionSelectorService $questionSelectorService)
    {
        $this->examService = $examService;
        $this->questionSelectorService = $questionSelectorService;
    }

    /**
     * Get all exams
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/api/exams",
     *     summary="Get all exams",
     *     tags={"Exams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Exams retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Exam"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $result = $this->examService->getAllExams($filters);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get exam by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
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
     * @param int $examId
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Post(
     *     path="/api/exam-questions/{examId}/select-from-banks",
     *     summary="Select questions from question banks for exam",
     *     tags={"Exam Questions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="examId",
     *         in="path",
     *         description="ID of the exam",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"question_banks"},
     *             @OA\Property(
     *                 property="question_banks",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"question_group_id", "question_count"},
     *                     @OA\Property(property="question_group_id", type="integer", example=1),
     *                     @OA\Property(property="question_count", type="integer", example=10),
     *                     @OA\Property(property="random_selection", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Questions selected successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Questions successfully selected for exam"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function selectQuestionsFromBanks(Request $request, $examId)
    {
        $request->validate([
            'question_banks' => 'required|array|min:1',
            'question_banks.*.question_group_id' => 'required|integer|exists:question_groups,id',
            'question_banks.*.question_count' => 'required|integer|min:1',
            'question_banks.*.start_range' => 'nullable|integer|min:1',
            'question_banks.*.end_range' => 'nullable|integer|min:1|gte:question_banks.*.start_range',
            'question_banks.*.random_selection' => 'boolean',
            'question_banks.*.weight' => 'nullable|integer|min:1|max:10',
        ]);

        $result = $this->questionSelectorService->selectQuestionsForExam($examId, $request->question_banks);

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
     * @param int $examId
     * @return \Illuminate\Http\JsonResponse
     */
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
     * Start exam for authenticated user
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
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
