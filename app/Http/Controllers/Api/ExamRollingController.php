<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExamRollingService;
use App\Services\ExamRollingBackgroundService;
use Illuminate\Http\Request;

/**
 * ExamRolling Controller
 *
 * Controller ini menangani rolling user yang mengikuti ujian
 * 
 * @OA\Tag(
 *     name="Exam Rolling",
 *     description="API endpoints for exam rolling operations"
 * )
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class ExamRollingController extends Controller
{
    /**
     * @var ExamRollingService
     */
    protected ExamRollingService $examRollingService;

    /**
     * @var ExamRollingBackgroundService
     */
    protected ExamRollingBackgroundService $backgroundService;

    /**
     * ExamRollingController constructor
     *
     * @param ExamRollingService $examRollingService
     * @param ExamRollingBackgroundService $backgroundService
     */
    public function __construct(ExamRollingService $examRollingService, ExamRollingBackgroundService $backgroundService)
    {
        $this->examRollingService = $examRollingService;
        $this->backgroundService = $backgroundService;
    }

    /**
     * Roll student to different group during exam
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rollStudent(Request $request)
    {
        $request->validate([
            'user_exam_id' => 'required|exists:user_exams,id',
            'new_group_id' => 'required|exists:student_groups,id',
        ]);

        $result = $this->examRollingService->rollStudentToGroup(
            $request->user_exam_id,
            $request->new_group_id
        );

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Validate rolling operation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateRolling(Request $request)
    {
        $request->validate([
            'user_exam_id' => 'required|exists:user_exams,id',
            'new_group_id' => 'required|exists:student_groups,id',
        ]);

        $result = $this->examRollingService->validateRolling(
            $request->user_exam_id,
            $request->new_group_id
        );
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Roll multiple students to different groups during exam
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rollMultipleStudents(Request $request)
    {
        $request->validate([
            'rolls' => 'required|array|min:1',
            'rolls.*.user_exam_id' => 'required|exists:user_exams,id',
            'rolls.*.new_group_id' => 'required|exists:student_groups,id',
        ]);

        $result = $this->examRollingService->rollMultipleStudents($request->rolls);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get students that can be rolled during exam
     *
     * @param Request $request
     * @param int $examId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRollableStudents(Request $request, $examId)
    {
        $result = $this->examRollingService->getRollableStudents($examId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Roll student to different group during exam with background job
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rollStudentWithBackgroundJob(Request $request)
    {
        $request->validate([
            'user_exam_id' => 'required|exists:user_exams,id',
            'new_group_id' => 'required|exists:student_groups,id',
        ]);

        $result = $this->backgroundService->rollStudentToGroupWithBackgroundJob(
            $request->user_exam_id,
            $request->new_group_id
        );

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Roll multiple students to different groups during exam with background jobs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rollMultipleStudentsWithBackgroundJobs(Request $request)
    {
        $request->validate([
            'rolls' => 'required|array|min:1',
            'rolls.*.user_exam_id' => 'required|exists:user_exams,id',
            'rolls.*.new_group_id' => 'required|exists:student_groups,id',
        ]);

        $result = $this->backgroundService->rollMultipleStudentsWithBackgroundJobs($request->rolls);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Process exam for all students in a group with background jobs
     *
     * @param Request $request
     * @param int $examId
     * @return \Illuminate\Http\JsonResponse
     */
    public function processExamForGroup(Request $request, $examId)
    {
        $request->validate([
            'group_id' => 'required|exists:student_groups,id',
        ]);

        $result = $this->backgroundService->processExamForGroup($examId, $request->group_id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
