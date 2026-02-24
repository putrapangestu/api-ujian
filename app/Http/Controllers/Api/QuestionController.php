<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionRequest;
use App\Services\QuestionService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * Question Controller
 *
 * Controller ini menangani semua operasi terkait soal
 *
 * @OA\Tag(
 *     name="Questions",
 *     description="API endpoints for managing questions"
 * )
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class QuestionController extends Controller
{
    /**
     * @var QuestionService
     */
    protected QuestionService $questionService;

    /**
     * QuestionController constructor
     *
     * @param QuestionService $questionService
     */
    public function __construct(QuestionService $questionService)
    {
        $this->questionService = $questionService;
    }

    /**
     * Create new question
     *
     * @param QuestionRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/questions",
     *     summary="Create new question",
     *     tags={"Questions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Question created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(QuestionRequest $request)
    {
        $result = $this->questionService->createQuestion($request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Update question
     *
     * @param QuestionRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Put(
     *     path="/questions/{id}",
     *     summary="Update question",
     *     tags={"Questions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID soal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question not found"
     *     )
     * )
     */
    public function update(QuestionRequest $request, $id)
    {
        $result = $this->questionService->updateQuestion($id, $request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Import questions from Excel file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/questions/import",
     *     summary="Import questions from Excel file",
     *     tags={"Questions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file","exam_id"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel file berisi data soal"
     *                 ),
     *                 @OA\Property(
     *                     property="exam_id",
     *                     type="integer",
     *                     description="ID ujian terkait"
     *                 ),
     *                 @OA\Property(
     *                     property="question_group_id",
     *                     type="integer",
     *                     nullable=true,
     *                     description="ID grup soal (opsional)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Questions imported successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
            'exam_id' => 'required|exists:exams,id',
            'question_group_id' => 'nullable|exists:question_groups,id',
        ]);

        $file = $request->file('file');
        $examId = $request->exam_id;
        $questionGroupId = $request->question_group_id;

        $result = $this->questionService->importQuestions($file, $examId, $questionGroupId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
