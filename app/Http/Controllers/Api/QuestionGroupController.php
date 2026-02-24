<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionGroupRequest;
use App\Services\QuestionGroupService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * QuestionGroup Controller
 *
 * Controller ini menangani semua operasi terkait grup soal/bank soal
 *
 * @OA\Tag(
 *     name="Question Groups",
 *     description="API endpoints for managing question groups"
 * )
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
     *
     * @OA\Get(
     *     path="/question-groups",
     *     summary="Get all question groups",
     *     tags={"Question Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
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
     *
     * @OA\Get(
     *     path="/question-groups/all",
     *     summary="Get all question groups without pagination",
     *     tags={"Question Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
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
     *
     * @OA\Get(
     *     path="/question-groups/{id}",
     *     summary="Get question group by ID",
     *     tags={"Question Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup soal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question group not found"
     *     )
     * )
     */
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
     *
     * @OA\Post(
     *     path="/admin/question-groups",
     *     summary="Create new question group",
     *     tags={"Question Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Question group created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
     */
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
     *
     * @OA\Put(
     *     path="/admin/question-groups/{id}",
     *     summary="Update question group",
     *     tags={"Question Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup soal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question group updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question group not found"
     *     )
     * )
     */
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
     *
     * @OA\Delete(
     *     path="/admin/question-groups/{id}",
     *     summary="Delete question group",
     *     tags={"Question Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup soal",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question group deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question group not found"
     *     )
     * )
     */
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
     *
     * @OA\Post(
     *     path="/question-groups/import",
     *     summary="Import question groups from Excel file",
     *     tags={"Question Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel file berisi data grup soal"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question groups imported successfully"
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
     *
     * @OA\Get(
     *     path="/question-groups/export",
     *     summary="Export question groups to Excel file",
     *     tags={"Question Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Question groups exported successfully"
     *     )
     * )
     */
    public function export(Request $request)
    {
        $result = $this->questionGroupService->exportGroups();

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
