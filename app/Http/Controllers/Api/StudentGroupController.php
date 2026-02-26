<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentGroupRequest;
use App\Services\StudentGroupService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * StudentGroup Controller
 *
 * Controller ini menangani semua operasi terkait grup siswa
 * Termasuk rolling siswa antar grup
 *
 * @author Development Team
 *
 * @version 1.0.0
 */
class StudentGroupController extends Controller
{
    protected StudentGroupService $studentGroupService;

    /**
     * StudentGroupController constructor
     */
    public function __construct(StudentGroupService $studentGroupService)
    {
        $this->studentGroupService = $studentGroupService;
    }

    /**
     * Get all student groups
     *
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(path: '/student-groups', summary: 'Daftar grup siswa', tags: ['Student Groups'], responses: [new OA\Response(response: 200, description: 'Success')])]
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
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(path: '/student-groups/all', summary: 'Daftar semua grup siswa', tags: ['Student Groups'], responses: [new OA\Response(response: 200, description: 'Success')])]
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
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(path: '/student-groups/{id}', summary: 'Detail grup siswa', tags: ['Student Groups'], responses: [new OA\Response(response: 200, description: 'Success')])]
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
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(path: '/admin/student-groups', summary: 'Buat grup siswa', tags: ['Admin', 'Student Groups'], responses: [new OA\Response(response: 200, description: 'Success')])]
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
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Put(path: '/admin/student-groups/{id}', summary: 'Perbarui grup siswa', tags: ['Admin', 'Student Groups'], responses: [new OA\Response(response: 200, description: 'Success')])]
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
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Delete(path: '/admin/student-groups/{id}', summary: 'Hapus grup siswa', tags: ['Admin', 'Student Groups'], responses: [new OA\Response(response: 200, description: 'Success')])]
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
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(path: '/student-groups/roll-students', summary: 'Rolling siswa antar grup', tags: ['Student Groups'], responses: [new OA\Response(response: 200, description: 'Success')])]
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

    #[OA\Post(path: '/student-groups/roll-multiple', summary: 'Rolling banyak siswa ke grup', tags: ['Student Groups'], responses: [new OA\Response(response: 200, description: 'Success')])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'rolls', type: 'array', items: new OA\Items(type: 'object')),
    ]))]
    public function rollMultipleStudents(Request $request)
    {
        $request->validate([
            'rolls' => 'required|array|min:1',
            'rolls.*.student_id' => 'required|uuid',
            'rolls.*.new_group_id' => 'required|uuid',
        ]);

        $result = $this->studentGroupService->rollMultipleStudents($request->rolls);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
