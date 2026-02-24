<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Services\StudentService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * Student Controller
 *
 * Controller ini menangani semua operasi terkait siswa
 * Termasuk fitur import dan export
 *
 * @OA\Tag(
 *     name="Students",
 *     description="API endpoints for managing students"
 * )
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
     *
     * @OA\Get(
     *     path="/students",
     *     summary="Get all students",
     *     tags={"Students"},
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
     *
     * @OA\Get(
     *     path="/students/{id}",
     *     summary="Get student by ID",
     *     tags={"Students"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID siswa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Student not found"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/admin/students",
     *     summary="Create new student",
     *     tags={"Students"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Student created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
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
     *
     * @OA\Put(
     *     path="/admin/students/{id}",
     *     summary="Update student",
     *     tags={"Students"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID siswa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Student updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Student not found"
     *     )
     * )
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
     *
     * @OA\Delete(
     *     path="/admin/students/{id}",
     *     summary="Delete student",
     *     tags={"Students"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID siswa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Student deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Student not found"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/students/import",
     *     summary="Import students from Excel file",
     *     tags={"Students"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file","group_id"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel file berisi data siswa"
     *                 ),
     *                 @OA\Property(
     *                     property="group_id",
     *                     type="integer",
     *                     description="ID grup siswa tujuan"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Students imported successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/students/export/{groupId}",
     *     summary="Export students to Excel file",
     *     tags={"Students"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         description="ID grup siswa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Students exported successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group not found"
     *     )
     * )
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
