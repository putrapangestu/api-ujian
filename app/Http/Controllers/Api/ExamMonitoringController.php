<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExamMonitoringService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

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
    #[OA\Post(
        path: "/exam-monitoring/record-action",
        summary: "Catat aksi ujian",
        tags: ["Exam Monitoring"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_exam_id", type: "integer"),
                    new OA\Property(property: "student_id", type: "integer"),
                    new OA\Property(property: "exam_id", type: "integer"),
                    new OA\Property(property: "action", type: "string", enum: ["start","answer","navigate","submit","pause","resume"]),
                    new OA\Property(property: "question_id", type: "integer"),
                    new OA\Property(property: "answer", type: "string"),
                    new OA\Property(property: "time_spent", type: "integer", minimum: 0),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/realtime/{examId}",
        summary: "Monitoring realtime ujian",
        tags: ["Exam Monitoring"],
        parameters: [new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     * @param string $studentId
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/student/{studentId}/{examId}",
        summary: "Monitoring detail siswa",
        tags: ["Exam Monitoring"],
        parameters: [
            new OA\Parameter(name: "studentId", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/summary/{examId}",
        summary: "Ringkasan monitoring ujian",
        tags: ["Exam Monitoring"],
        parameters: [new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     * @param string $examId
     * @param string $questionGroupId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/by-group/{examId}/{questionGroupId}",
        summary: "Monitoring per grup soal",
        tags: ["Exam Monitoring"],
        parameters: [
            new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "questionGroupId", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/by-category/{examId}",
        summary: "Monitoring per kategori",
        tags: ["Exam Monitoring"],
        parameters: [new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getMonitoringByCategory(Request $request, $examId)
    {
        $result = $this->examMonitoringService->getMonitoringByCategory($examId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
