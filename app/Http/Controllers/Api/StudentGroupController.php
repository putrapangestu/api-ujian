<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentGroupRequest;
use App\Services\StudentGroupService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * StudentGroup Controller
 *
 * Controller ini menangani semua operasi terkait grup siswa
 * Termasuk rolling siswa antar grup
 *
 * @OA\Tag(
 *     name="Student Groups",
 *     description="API endpoints for managing student groups"
 * )
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class StudentGroupController extends Controller
{
    /**
     * @var StudentGroupService
     */
    protected StudentGroupService $studentGroupService;

    /**
     * StudentGroupController constructor
     *
     * @param StudentGroupService $studentGroupService
     */
    public function __construct(StudentGroupService $studentGroupService)
    {
        $this->studentGroupService = $studentGroupService;
    }

    /**
     * Get all student groups
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/student-groups",
     *     summary="Get all student groups",
     *     tags={"Student Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $perPage = (int) $request->get('per_page', 15);
        $result = $this->studentGroupService->getAllGroups($filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Get all student groups without pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/student-groups/all",
     *     summary="Get all student groups without pagination",
     *     tags={"Student Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function listAll(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $result = $this->studentGroupService->getAllGroupsList($filters);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get student group by ID
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/student-groups/{id}",
     *     summary="Get student group by ID",
     *     tags={"Student Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup siswa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Student group not found"
     *     )
     * )
     */
    public function show($id)
    {
        $result = $this->studentGroupService->getGroupById($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Create new student group
     *
     * @param StudentGroupRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/admin/student-groups",
     *     summary="Create new student group",
     *     tags={"Student Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object"
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Student group created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function store(StudentGroupRequest $request)
    {
        $result = $this->studentGroupService->createGroup($request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Update student group
     *
     * @param StudentGroupRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Put(
     *     path="/admin/student-groups/{id}",
     *     summary="Update student group",
     *     tags={"Student Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup siswa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Student group updated successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Student group not found"
     *     )
     * )
     */
    public function update(StudentGroupRequest $request, $id)
    {
        $result = $this->studentGroupService->updateGroup($id, $request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Delete student group
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Delete(
     *     path="/admin/student-groups/{id}",
     *     summary="Delete student group",
     *     tags={"Student Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup siswa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Student group deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Student group not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $result = $this->studentGroupService->deleteGroup($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Roll students to new group
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/student-groups/roll-students",
     *     summary="Roll students to new group",
     *     tags={"Student Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"from_group_id","to_group_id","student_ids"},
     *             @OA\Property(property="from_group_id", type="integer", example=1),
     *             @OA\Property(property="to_group_id", type="integer", example=2),
     *             @OA\Property(
     *                 property="student_ids",
     *                 type="array",
     *                 @OA\Items(type="integer", example=1)
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
    public function rollStudents(Request $request)
    {
        $request->validate([
            'from_group_id' => 'required|exists:student_groups,id',
            'to_group_id' => 'required|exists:student_groups,id|different:from_group_id',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $result = $this->studentGroupService->rollStudents(
            $request->from_group_id,
            $request->to_group_id,
            $request->student_ids
        );

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
