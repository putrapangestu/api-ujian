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
 * @author Development Team
 *
 * @version 1.0.0
 */
class QuestionController extends Controller
{
    protected QuestionService $questionService;

    /**
     * QuestionController constructor
     */
    public function __construct(QuestionService $questionService)
    {
        $this->questionService = $questionService;
    }

    /**
     * Get all questions (paginated)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/questions',
        summary: 'Daftar soal',
        tags: ['Questions'],
        parameters: [
            new OA\Parameter(name: 'question_group_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'question_category_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function index(Request $request)
    {
        $filters = $request->only(['question_group_id', 'question_category_id', 'category_id', 'search']);
        $perPage = (int) $request->get('per_page', 15);
        $result = $this->questionService->getAllQuestions($filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Create new question
     *
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: '/questions',
        summary: 'Buat soal',
        tags: ['Questions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'exam_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'category_id', type: 'integer'),
                    new OA\Property(property: 'question_group_id', type: 'integer'),
                    new OA\Property(property: 'question_text', type: 'string', maxLength: 1000),
                    new OA\Property(property: 'question_type', type: 'string', enum: ['multiple_choice', 'essay', 'true_false', 'single_choice']),
                    new OA\Property(property: 'score', type: 'integer', minimum: 1, maximum: 100),
                    new OA\Property(property: 'options', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'correct_answer', type: 'string'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
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
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Put(
        path: '/questions/{id}',
        summary: 'Perbarui soal',
        tags: ['Questions'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'exam_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'question_category_id', type: 'string'),
                    new OA\Property(property: 'question_group_id', type: 'string'),
                    new OA\Property(property: 'question_text', type: 'string', maxLength: 1000),
                    new OA\Property(property: 'question_type', type: 'string', enum: ['multiple_choice', 'essay', 'true_false', 'single_choice']),
                    new OA\Property(property: 'score', type: 'integer', minimum: 1, maximum: 100),
                    new OA\Property(property: 'options', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'correct_answer', type: 'string'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
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
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: '/questions/import',
        summary: 'Import soal',
        tags: ['Questions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'exam_id', type: 'integer'),
                        new OA\Property(property: 'question_group_id', type: 'integer'),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
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
