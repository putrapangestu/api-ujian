<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionCategoryRequest;
use App\Services\QuestionCategoryService;
use Illuminate\Http\Request;

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