<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentGroupRequest;
use App\Services\StudentGroupService;
use Illuminate\Http\Request;

/**
 * StudentGroup Controller
 * 
 * Controller ini menangani semua operasi terkait grup siswa
 * Termasuk rolling siswa antar grup
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
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $result = $this->studentGroupService->getAllGroups($filters);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get student group by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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