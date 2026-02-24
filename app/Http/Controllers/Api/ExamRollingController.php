<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExamRollingService;
use App\Services\ExamRollingBackgroundService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

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
     *
     * @OA\Post(
     *     path="/exam-rolling/roll-student",
     *     summary="Roll single student to different group during exam",
     *     tags={"Exam Rolling"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"user_exam_id","new_group_id"},
     *             @OA\Property(property="user_exam_id", type="integer"),
     *             @OA\Property(property="new_group_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Student rolled successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/exam-rolling/validate",
     *     summary="Validate rolling operation",
     *     tags={"Exam Rolling"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"user_exam_id","new_group_id"},
     *             @OA\Property(property="user_exam_id", type="integer"),
     *             @OA\Property(property="new_group_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rolling operation is valid"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/exam-rolling/roll-multiple",
     *     summary="Roll multiple students to different groups during exam",
     *     tags={"Exam Rolling"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"rolls"},
     *             @OA\Property(
     *                 property="rolls",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"user_exam_id","new_group_id"},
     *                     @OA\Property(property="user_exam_id", type="integer"),
     *                     @OA\Property(property="new_group_id", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Students rolled successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/exam-rolling/rollable-students/{examId}",
     *     summary="Get students that can be rolled during exam",
     *     tags={"Exam Rolling"},
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
     *         description="Rollable students retrieved successfully"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/exam-rolling/roll-student-background",
     *     summary="Roll student with background job",
     *     tags={"Exam Rolling"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"user_exam_id","new_group_id"},
     *             @OA\Property(property="user_exam_id", type="integer"),
     *             @OA\Property(property="new_group_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Background job dispatched successfully"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/exam-rolling/roll-multiple-background",
     *     summary="Roll multiple students with background jobs",
     *     tags={"Exam Rolling"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"rolls"},
     *             @OA\Property(
     *                 property="rolls",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"user_exam_id","new_group_id"},
     *                     @OA\Property(property="user_exam_id", type="integer"),
     *                     @OA\Property(property="new_group_id", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Background jobs dispatched successfully"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/exam-rolling/process-group/{examId}",
     *     summary="Process exam for all students in a group",
     *     tags={"Exam Rolling"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="examId",
     *         in="path",
     *         description="ID ujian",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"group_id"},
     *             @OA\Property(property="group_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Background job dispatched successfully"
     *     )
     * )
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
