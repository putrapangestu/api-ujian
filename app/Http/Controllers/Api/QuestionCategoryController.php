<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionCategoryRequest;
use App\Services\QuestionCategoryService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * QuestionCategory Controller
 *
 * Controller ini menangani semua operasi terkait kategori soal
 *
 * @OA\Tag(
 *     name="Question Categories",
 *     description="API endpoints for managing question categories"
 * )
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
     *
     * @OA\Get(
     *     path="/question-categories",
     *     summary="Get all question categories",
     *     tags={"Question Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $result = $this->questionCategoryService->getAllCategories($filters);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get question category by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/question-categories/{id}",
     *     summary="Get question category by ID",
     *     tags={"Question Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID kategori soal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question category not found"
     *     )
     * )
     */
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
     *
     * @OA\Post(
     *     path="/admin/question-categories",
     *     summary="Create new question category",
     *     tags={"Question Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Question category created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
     */
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Put(
     *     path="/admin/question-categories/{id}",
     *     summary="Update question category",
     *     tags={"Question Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID kategori soal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question category updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question category not found"
     *     )
     * )
     */
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Delete(
     *     path="/admin/question-categories/{id}",
     *     summary="Delete question category",
     *     tags={"Question Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID kategori soal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question category deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question category not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $result = $this->questionCategoryService->deleteCategory($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
