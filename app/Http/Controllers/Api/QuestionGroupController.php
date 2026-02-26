<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionGroupRequest;
use App\Services\QuestionGroupService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * QuestionGroup Controller
 *
 * Controller ini menangani semua operasi terkait grup soal/bank soal
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class QuestionGroupController extends Controller
{
    /**
     * @var QuestionGroupService
     */
    protected QuestionGroupService $questionGroupService;

    /**
     * QuestionGroupController constructor
     *
     * @param QuestionGroupService $questionGroupService
     */
    public function __construct(QuestionGroupService $questionGroupService)
    {
        $this->questionGroupService = $questionGroupService;
    }

    /**
     * Get all question groups
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/question-groups",
        summary: "Daftar grup soal",
        tags: ["Question Groups"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $perPage = (int) $request->get('per_page', 15);
        $result = $this->questionGroupService->getAllGroups($filters, $perPage);

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json($result, 400);
    }

    /**
     * Get all question groups without pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/question-groups/all",
        summary: "Daftar semua grup soal",
        tags: ["Question Groups"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function listAll(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $result = $this->questionGroupService->getAllGroupsList($filters);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get question group by ID
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/question-groups/{id}",
        summary: "Detail grup soal",
        tags: ["Question Groups"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function show($id)
    {
        $result = $this->questionGroupService->getGroupById($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Create new question group
     *
     * @param QuestionGroupRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/admin/question-groups",
        summary: "Buat grup soal",
        tags: ["Admin","Question Groups"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "code", type: "string", maxLength: 50),
                    new OA\Property(property: "status", type: "string"),
                    new OA\Property(property: "question_category_id", type: "string"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function store(QuestionGroupRequest $request)
    {
        $result = $this->questionGroupService->createGroup($request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Update question group
     *
     * @param QuestionGroupRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Put(
        path: "/admin/question-groups/{id}",
        summary: "Perbarui grup soal",
        tags: ["Admin","Question Groups"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "code", type: "string", maxLength: 50),
                    new OA\Property(property: "status", type: "string"),
                    new OA\Property(property: "question_category_id", type: "string"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function update(QuestionGroupRequest $request, $id)
    {
        $result = $this->questionGroupService->updateGroup($id, $request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Delete question group
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Delete(
        path: "/admin/question-groups/{id}",
        summary: "Hapus grup soal",
        tags: ["Admin","Question Groups"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function destroy($id)
    {
        $result = $this->questionGroupService->deleteGroup($id);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Import question groups from Excel file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/question-groups/import",
        summary: "Import grup soal",
        tags: ["Question Groups"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary"),
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
        ]);

        $file = $request->file('file');
        $result = $this->questionGroupService->importGroups($file);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Export question groups to Excel file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/question-groups/export",
        summary: "Export grup soal",
        tags: ["Question Groups"],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function export(Request $request)
    {
        $result = $this->questionGroupService->exportGroups();

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
