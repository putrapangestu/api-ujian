<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionGroupRequest;
use App\Services\QuestionGroupService;
use Illuminate\Http\Request;

/**
 * QuestionGroup Controller
 * 
 * Controller ini menangani semua operasi terkait grup soal/bank soal
 * 
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class QuestionGroupController extends Controller
{
    /**
     * @var QuestionGroupService
     */
    protected QuestionGroupService $questionGroupService;

    /**
     * QuestionGroupController constructor
     * 
     * @param QuestionGroupService $questionGroupService
     */
    public function __construct(QuestionGroupService $questionGroupService)
    {
        $this->questionGroupService = $questionGroupService;
    }

    /**
     * Get all question groups
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $result = $this->questionGroupService->getAllGroups($filters);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get question group by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $result = $this->questionGroupService->getGroupById($id);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Create new question group
     * 
     * @param QuestionGroupRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(QuestionGroupRequest $request)
    {
        $result = $this->questionGroupService->createGroup($request->validatedData());
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Update question group
     * 
     * @param QuestionGroupRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(QuestionGroupRequest $request, $id)
    {
        $result = $this->questionGroupService->updateGroup($id, $request->validatedData());
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Delete question group
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $result = $this->questionGroupService->deleteGroup($id);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Import question groups from Excel file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $result = $this->questionGroupService->importGroups($file);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Export question groups to Excel file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(Request $request)
    {
        $result = $this->questionGroupService->exportGroups();
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}