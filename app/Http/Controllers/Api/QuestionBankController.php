<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuestionBankService;
use Illuminate\Http\Request;

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