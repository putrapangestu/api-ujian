<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Services\StudentService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

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
    #[OA\Get(
        path: "/students",
        summary: "Daftar siswa",
        tags: ["Students"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "group_id", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'group_id', 'status']);
        $perPage = (int) $request->get('per_page', 15);
        $result = $this->studentService->getAllStudents($filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Get all students without pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/students/all",
        summary: "Daftar semua siswa",
        tags: ["Students"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "group_id", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function list(Request $request)
    {
        $filters = $request->only(['search', 'group_id', 'status']);
        $result = $this->studentService->getListAllStudents($filters);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get student by ID
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/students/{id}",
        summary: "Detail siswa",
        tags: ["Students"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function show($id)
    {
        $result = $this->studentService->getStudentById($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get student exam history with pagination and filtering
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/students/{id}/exam-history",
        summary: "Riwayat ujian siswa",
        tags: ["Students"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"), description: "Cari nama ujian"),
            new OA\Parameter(name: "start_date", in: "query", schema: new OA\Schema(type: "string", format: "date"), description: "Filter tanggal awal ujian"),
            new OA\Parameter(name: "end_date", in: "query", schema: new OA\Schema(type: "string", format: "date"), description: "Filter tanggal akhir ujian"),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getExamHistory(Request $request, $id)
    {
        $filters = $request->only(['search', 'start_date', 'end_date', 'per_page']);
        $result = $this->studentService->getStudentExamHistory($id, $filters);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Export student exam history to Excel file
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/students/{id}/exam-history/export",
        summary: "Export riwayat ujian siswa",
        tags: ["Students"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"), description: "Cari nama ujian"),
            new OA\Parameter(name: "start_date", in: "query", schema: new OA\Schema(type: "string", format: "date"), description: "Filter tanggal awal ujian"),
            new OA\Parameter(name: "end_date", in: "query", schema: new OA\Schema(type: "string", format: "date"), description: "Filter tanggal akhir ujian"),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function exportExamHistory(Request $request, $id)
    {
        $filters = $request->only(['search', 'start_date', 'end_date']);
        $result = $this->studentService->exportStudentExamHistory($id, $filters);

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
    #[OA\Post(
        path: "/admin/students",
        summary: "Buat siswa",
        tags: ["Admin","Students"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "nisn", type: "string", maxLength: 20),
                    new OA\Property(property: "name", type: "string", maxLength: 255),
                    new OA\Property(property: "email", type: "string", format: "email", maxLength: 255),
                    new OA\Property(property: "phone", type: "string", maxLength: 20),
                    new OA\Property(property: "address", type: "string", maxLength: 500),
                    new OA\Property(property: "group_id", type: "integer"),
                    new OA\Property(property: "birth_date", type: "string", format: "date"),
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "status", type: "string"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Put(
        path: "/admin/students/{id}",
        summary: "Perbarui siswa",
        tags: ["Admin","Students"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "nisn", type: "string", maxLength: 20),
                    new OA\Property(property: "name", type: "string", maxLength: 255),
                    new OA\Property(property: "email", type: "string", format: "email", maxLength: 255),
                    new OA\Property(property: "phone", type: "string", maxLength: 20),
                    new OA\Property(property: "address", type: "string", maxLength: 500),
                    new OA\Property(property: "group_id", type: "integer"),
                    new OA\Property(property: "birth_date", type: "string", format: "date"),
                    new OA\Property(property: "user_id", type: "integer"),
                    new OA\Property(property: "status", type: "string"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Delete(
        path: "/admin/students/{id}",
        summary: "Hapus siswa",
        tags: ["Admin","Students"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function destroy($id)
    {
        $result = $this->studentService->deleteStudent($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Delete multiple students
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/admin/students/bulk-delete",
        summary: "Hapus banyak siswa sekaligus",
        tags: ["Admin","Students"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: "student_ids",
                        type: "array",
                        items: new OA\Items(type: "string")
                    ),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function destroyBulk(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|string|exists:students,id',
        ]);

        $result = $this->studentService->deleteMultipleStudents($request->student_ids);

        if ($result['success']) {
            return $this->successResponse($result['data'] ?? null, $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Download Excel template for importing students
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/students/download-template",
        summary: "Download template Excel untuk import siswa",
        tags: ["Students"],
        responses: [
            new OA\Response(
                response: 200,
                description: "File Excel template berhasil diunduh",
                content: new OA\MediaType(
                    mediaType: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                )
            )
        ]
    )]
    public function downloadTemplate()
    {
        try {
            return $this->studentService->downloadTemplate();
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengunduh template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import students from Excel file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/students/import",
        summary: "Import data siswa",
        tags: ["Students"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary"),
                        new OA\Property(property: "group_id", type: "integer"),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
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
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/admin/students/export",
        summary: "Export data siswa",
        tags: ["Students", "Admin"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "group_id", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "File Excel export siswa",
                content: new OA\MediaType(
                    mediaType: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                )
            )
        ]
    )]
    public function export(Request $request)
    {
        $filters = $request->only(['search', 'group_id', 'status']);
        $result = $this->studentService->exportStudents($filters);

        if (isset($result['success']) && $result['success'] === true && isset($result['file_path'])) {
            return response()->download($result['file_path'], $result['file_name'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $result['file_name'] . '"'
            ])->deleteFileAfterSend(true);
        }

        return $this->errorResponse($result['message'] ?? 'Gagal melakukan export data siswa', 400, $result['errors'] ?? null);
    }
}
