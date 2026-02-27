<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionCategoryRequest;
use App\Services\QuestionCategoryService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * QuestionCategory Controller
 *
 * Controller ini menangani semua operasi terkait kategori soal
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class QuestionCategoryController extends Controller
{
    /**
     * @var QuestionCategoryService
     */
    protected QuestionCategoryService $questionCategoryService;

    /**
     * QuestionCategoryController constructor
     *
     * @param QuestionCategoryService $questionCategoryService
     */
    public function __construct(QuestionCategoryService $questionCategoryService)
    {
        $this->questionCategoryService = $questionCategoryService;
    }

    /**
     * Get all question categories
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/question-categories",
        summary: "Daftar kategori soal",
        tags: ["Question Categories"],
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
        $result = $this->questionCategoryService->getAllCategories($filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Get question category by ID
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/question-categories/{id}",
        summary: "Detail kategori soal",
        tags: ["Question Categories"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function show($id)
    {
        $result = $this->questionCategoryService->getCategoryById($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Create new question category
     *
     * @param QuestionCategoryRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/admin/question-categories",
        summary: "Buat kategori soal",
        tags: ["Admin","Question Categories"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "code", type: "string", maxLength: 50),
                    new OA\Property(property: "status", type: "string"),
                    new OA\Property(property: "start_range", type: "integer"),
                    new OA\Property(property: "end_range", type: "integer"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function store(QuestionCategoryRequest $request)
    {
        $result = $this->questionCategoryService->createCategory($request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Update question category
     *
     * @param QuestionCategoryRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Put(
        path: "/admin/question-categories/{id}",
        summary: "Perbarui kategori soal",
        tags: ["Admin","Question Categories"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "code", type: "string", maxLength: 50),
                    new OA\Property(property: "status", type: "string"),
                    new OA\Property(property: "start_range", type: "integer"),
                    new OA\Property(property: "end_range", type: "integer"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function update(QuestionCategoryRequest $request, $id)
    {
        $result = $this->questionCategoryService->updateCategory($id, $request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Delete question category
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Delete(
        path: "/admin/question-categories/{id}",
        summary: "Hapus kategori soal",
        tags: ["Admin","Question Categories"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function destroy($id)
    {
        $result = $this->questionCategoryService->deleteCategory($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
