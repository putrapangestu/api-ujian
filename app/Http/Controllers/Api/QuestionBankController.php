<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuestionBankService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * QuestionBank Controller
 *
 * Controller ini menangani import/export bank soal
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
     */
    #[OA\Post(
        path: "/question-bank/import",
        summary: "Import bank soal",
        tags: ["Question Bank"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary"),
                        new OA\Property(property: "exam_id", type: "integer"),
                        new OA\Property(property: "question_group_id", type: "integer"),
                        new OA\Property(property: "category_id", type: "integer"),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     */
    #[OA\Get(
        path: "/question-bank/export",
        summary: "Export bank soal",
        tags: ["Question Bank"],
        parameters: [
            new OA\Parameter(name: "exam_id", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "question_group_id", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "category_id", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
