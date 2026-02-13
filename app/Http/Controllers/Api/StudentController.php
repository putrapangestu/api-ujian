<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Services\StudentService;
use Illuminate\Http\Request;

/**
 * Student Controller
 * 
 * Controller ini menangani semua operasi terkait siswa
 * Termasuk fitur import dan export
 * 
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class StudentController extends Controller
{
    /**
     * @var StudentService
     */
    protected StudentService $studentService;

    /**
     * StudentController constructor
     * 
     * @param StudentService $studentService
     */
    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * Get all students
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'group_id', 'status']);
        $result = $this->studentService->getAllStudents($filters);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get student by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $result = $this->studentService->getStudentById($id);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Create new student
     * 
     * @param StudentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StudentRequest $request)
    {
        $result = $this->studentService->createStudent($request->validatedData());
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Update student
     * 
     * @param StudentRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(StudentRequest $request, $id)
    {
        $result = $this->studentService->updateStudent($id, $request->validatedData());
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Delete student
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $result = $this->studentService->deleteStudent($id);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Import students from Excel file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
            'group_id' => 'required|exists:student_groups,id',
        ]);

        $file = $request->file('file');
        $groupId = $request->group_id;

        $result = $this->studentService->importStudents($file, $groupId);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Export students to Excel file
     * 
     * @param Request $request
     * @param int $groupId
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(Request $request, $groupId)
    {
        $result = $this->studentService->exportStudents($groupId);
        
        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }
        
        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}