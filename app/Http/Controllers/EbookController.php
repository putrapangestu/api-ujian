<?php

namespace App\Http\Controllers;

use App\Services\EbookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EbookController extends Controller
{
    protected EbookService $ebookService;

    public function __construct(EbookService $ebookService)
    {
        $this->ebookService = $ebookService;
    }

    public function index(Request $request): JsonResponse
    {
        $response = $this->ebookService->getAllEbooks($request);
        return response()->json($response, $response['code'] ?? 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,epub,doc,docx,txt|max:10240', // 10MB max
            'question_category_id' => 'nullable|string|exists:question_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
                'code' => 422
            ], 422);
        }

        $response = $this->ebookService->createEbook($request);
        return response()->json($response, $response['code'] ?? 201);
    }

    public function show(string $id): JsonResponse
    {
        $response = $this->ebookService->getEbookById($id);
        return response()->json($response, $response['code'] ?? 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'file' => 'nullable|file|mimes:pdf,epub,doc,docx,txt|max:10240',
            'question_category_id' => 'nullable|string|exists:question_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
                'code' => 422
            ], 422);
        }

        $response = $this->ebookService->updateEbook($id, $request);
        return response()->json($response, $response['code'] ?? 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $response = $this->ebookService->deleteEbook($id);
        return response()->json($response, $response['code'] ?? 200);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|string|exists:ebooks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
                'code' => 422
            ], 422);
        }

        $response = $this->ebookService->bulkDeleteEbooks($request->ids);
        return response()->json($response, $response['code'] ?? 200);
    }

    public function download(string $id)
    {
        $response = $this->ebookService->getEbookFileForDownload($id);

        if (isset($response['success']) && !$response['success']) {
            return response()->json($response, $response['code'] ?? 404);
        }

        return response()->download($response['path'], $response['original_name']);
    }
}