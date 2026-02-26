<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionRequest;
use App\Services\QuestionService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Question Controller
 *
 * Controller ini menangani semua operasi terkait soal
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
     */
    #[OA\Post(
        path: "/questions",
        summary: "Buat soal",
        tags: ["Questions"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "exam_id", type: "integer", nullable: true),
                    new OA\Property(property: "category_id", type: "integer"),
                    new OA\Property(property: "question_group_id", type: "integer"),
                    new OA\Property(property: "question_text", type: "string", maxLength: 1000),
                    new OA\Property(property: "question_type", type: "string", enum: ["multiple_choice","essay","true_false"]),
                    new OA\Property(property: "score", type: "integer", minimum: 1, maximum: 100),
                    new OA\Property(property: "options", type: "array", items: new OA\Items(type: "object")),
                    new OA\Property(property: "correct_answer", type: "string"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     */
    #[OA\Put(
        path: "/questions/{id}",
        summary: "Perbarui soal",
        tags: ["Questions"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "exam_id", type: "integer", nullable: true),
                    new OA\Property(property: "question_category_id", type: "string"),
                    new OA\Property(property: "question_group_id", type: "string"),
                    new OA\Property(property: "question_text", type: "string", maxLength: 1000),
                    new OA\Property(property: "question_type", type: "string", enum: ["multiple_choice","essay","true_false"]),
                    new OA\Property(property: "score", type: "integer", minimum: 1, maximum: 100),
                    new OA\Property(property: "options", type: "array", items: new OA\Items(type: "object")),
                    new OA\Property(property: "correct_answer", type: "string"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     */
    #[OA\Post(
        path: "/questions/import",
        summary: "Import soal",
        tags: ["Questions"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary"),
                        new OA\Property(property: "exam_id", type: "integer"),
                        new OA\Property(property: "question_group_id", type: "integer"),
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
