<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuestionBankService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * QuestionBank Controller
 *
 * Controller ini menangani import/export bank soal
 *
 * @OA\Tag(
 *     name="Question Bank",
 *     description="API endpoints for managing question bank import/export"
 * )
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class QuestionBankController extends Controller
{
    /**
     * @var QuestionBankService
     */
    protected QuestionBankService $questionBankService;

    /**
     * QuestionBankController constructor
     *
     * @param QuestionBankService $questionBankService
     */
    public function __construct(QuestionBankService $questionBankService)
    {
        $this->questionBankService = $questionBankService;
    }

    /**
     * Import question bank from Excel file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/question-bank/import",
     *     summary="Import question bank from Excel file",
     *     tags={"Question Bank"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel file berisi bank soal"
     *                 ),
     *                 @OA\Property(
     *                     property="exam_id",
     *                     type="integer",
     *                     nullable=true,
     *                     description="ID ujian terkait (opsional)"
     *                 ),
     *                 @OA\Property(
     *                     property="question_group_id",
     *                     type="integer",
     *                     nullable=true,
     *                     description="ID grup soal (opsional)"
     *                 ),
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="integer",
     *                     nullable=true,
     *                     description="ID kategori soal (opsional)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question bank imported successfully"
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
            'exam_id' => 'nullable|exists:exams,id',
            'question_group_id' => 'nullable|exists:question_groups,id',
            'category_id' => 'nullable|exists:question_categories,id',
        ]);

        $file = $request->file('file');
        $examId = $request->exam_id;
        $questionGroupId = $request->question_group_id;
        $categoryId = $request->category_id;

        $result = $this->questionBankService->importQuestionBank($file, $examId, $questionGroupId, $categoryId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Export question bank to Excel file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/question-bank/export",
     *     summary="Export question bank to Excel file",
     *     tags={"Question Bank"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="exam_id",
     *         in="query",
     *         description="Filter by exam ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="question_group_id",
     *         in="query",
     *         description="Filter by question group ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by question category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question bank exported successfully"
     *     )
     * )
     */
    public function export(Request $request)
    {
        $request->validate([
            'exam_id' => 'nullable|exists:exams,id',
            'question_group_id' => 'nullable|exists:question_groups,id',
            'category_id' => 'nullable|exists:question_categories,id',
        ]);

        $examId = $request->exam_id;
        $questionGroupId = $request->question_group_id;
        $categoryId = $request->category_id;

        $result = $this->questionBankService->exportQuestionBank($examId, $questionGroupId, $categoryId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
