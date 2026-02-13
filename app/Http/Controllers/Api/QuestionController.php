<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionRequest;
use App\Services\QuestionService;
use Illuminate\Http\Request;

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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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