<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExamMonitoringService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * ExamMonitoring Controller
 *
 * Controller ini menangani monitoring realtime pengerjaan ujian
 *
 * @OA\Tag(
 *     name="Exam Monitoring",
 *     description="API endpoints for exam monitoring"
 )
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
     *
     * @OA\Post(
     *     path="/exam-monitoring/record-action",
     *     summary="Record exam action for monitoring",
     *     tags={"Exam Monitoring"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"user_exam_id","student_id","exam_id","action"},
     *             @OA\Property(property="user_exam_id", type="integer"),
     *             @OA\Property(property="student_id", type="integer"),
     *             @OA\Property(property="exam_id", type="integer"),
     *             @OA\Property(
     *                 property="action",
     *                 type="string",
     *                 enum={"start","answer","navigate","submit","pause","resume"}
     *             ),
     *             @OA\Property(property="question_id", type="integer", nullable=true),
     *             @OA\Property(property="answer", type="string", nullable=true),
     *             @OA\Property(property="time_spent", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Action recorded successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/exam-monitoring/realtime/{examId}",
     *     summary="Get real-time exam monitoring data",
     *     tags={"Exam Monitoring"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="examId",
     *         in="path",
     *         description="ID ujian",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Monitoring data retrieved successfully"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/exam-monitoring/student/{studentId}/{examId}",
     *     summary="Get monitoring for specific student",
     *     tags={"Exam Monitoring"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="studentId",
     *         in="path",
     *         description="ID siswa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="examId",
     *         in="path",
     *         description="ID ujian",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Monitoring data retrieved successfully"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/exam-monitoring/summary/{examId}",
     *     summary="Get exam summary for monitoring",
     *     tags={"Exam Monitoring"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="examId",
     *         in="path",
     *         description="ID ujian",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Summary retrieved successfully"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/exam-monitoring/by-group/{examId}/{questionGroupId}",
     *     summary="Get monitoring by question group",
     *     tags={"Exam Monitoring"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="examId",
     *         in="path",
     *         description="ID ujian",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="questionGroupId",
     *         in="path",
     *         description="ID grup soal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Monitoring data retrieved successfully"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/exam-monitoring/by-category/{examId}",
     *     summary="Get monitoring by category",
     *     tags={"Exam Monitoring"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="examId",
     *         in="path",
     *         description="ID ujian",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Monitoring data retrieved successfully"
     *     )
     * )
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
