<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExamMonitoringService;
use Illuminate\Http\Request;

/**
 * ExamMonitoring Controller
 * 
 * Controller ini menangani monitoring realtime pengerjaan ujian
 * 
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class ExamMonitoringController extends Controller
{
    /**
     * @var ExamMonitoringService
     */
    protected ExamMonitoringService $examMonitoringService;

    /**
     * ExamMonitoringController constructor
     * 
     * @param ExamMonitoringService $examMonitoringService
     */
    public function __construct(ExamMonitoringService $examMonitoringService)
    {
        $this->examMonitoringService = $examMonitoringService;
    }

    /**
     * Record exam action for monitoring
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordAction(Request $request)
    {
        $request->validate([
            'user_exam_id' => 'required|exists:user_exams,id',
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'action' => 'required|in:start,answer,navigate,submit,pause,resume',
            'question_id' => 'nullable|exists:questions,id',
            'answer' => 'nullable|string',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $result = $this->examMonitoringService->recordAction($request->all());
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get real-time exam monitoring data
     * 
     * @param Request $request
     * @param int $examId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRealtimeMonitoring(Request $request, $examId)
    {
        $result = $this->examMonitoringService->getRealtimeMonitoring($examId);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get detailed monitoring for specific student
     * 
     * @param Request $request
     * @param int $studentId
     * @param int $examId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentMonitoring(Request $request, $studentId, $examId)
    {
        $result = $this->examMonitoringService->getStudentMonitoring($studentId, $examId);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get exam summary for monitoring dashboard
     * 
     * @param Request $request
     * @param int $examId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExamSummary(Request $request, $examId)
    {
        $result = $this->examMonitoringService->getExamSummary($examId);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get monitoring by question group
     * 
     * @param Request $request
     * @param int $examId
     * @param int $questionGroupId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMonitoringByQuestionGroup(Request $request, $examId, $questionGroupId)
    {
        $result = $this->examMonitoringService->getMonitoringByQuestionGroup($examId, $questionGroupId);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get monitoring by category
     * 
     * @param Request $request
     * @param int $examId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMonitoringByCategory(Request $request, $examId)
    {
        $result = $this->examMonitoringService->getMonitoringByCategory($examId);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}