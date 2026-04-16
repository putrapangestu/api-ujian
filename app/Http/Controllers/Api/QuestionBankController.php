<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuestionBankService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * QuestionBank Controller
 *
 * Controller ini menangani import/export bank soal
 *
 * @author Development Team
 *
 * @version 1.0.0
 */
class QuestionBankController extends Controller
{
    protected QuestionBankService $questionBankService;

    /**
     * QuestionBankController constructor
     */
    public function __construct(QuestionBankService $questionBankService)
    {
        $this->questionBankService = $questionBankService;
    }

    /**
     * Import question bank from Excel file
     *
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: '/question-bank/import',
        summary: 'Import bank soal',
        tags: ['Question Bank'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'exam_id', type: 'integer'),
                        new OA\Property(property: 'question_group_id', type: 'integer'),
                        new OA\Property(property: 'category_id', type: 'integer'),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
            'exam_id' => 'nullable|exists:exams,id',
            'question_group_id' => 'nullable|exists:question_groups,id',
            'category_id' => 'nullable|exists:question_categories,id',
        ]);

        $file = $request->file('file');
        $examId = $request->exam_id;
        $questionGroupId = $request->question_group_id;
        $categoryId = $request->category_id;

        $result = $this->questionBankService->importQuestionBank($file, $examId, $questionGroupId, $categoryId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    #[OA\Post(
        path: '/question-bank/import-moodle',
        summary: 'Import bank soal (Moodle XML)',
        tags: ['Question Bank'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'exam_id', type: 'string'),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function importMoodleXml(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xml|max:10240',
            'exam_id' => 'nullable|exists:exams,id',
        ]);

        $file = $request->file('file');
        $examId = $request->exam_id;

        $result = $this->questionBankService->importMoodleXml($file, $examId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    #[OA\Post(
        path: '/question-bank/import-examview',
        summary: 'Import bank soal dari ExamView (Blackboard ZIP)',
        tags: ['Question Bank'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'exam_id', type: 'integer'),
                        new OA\Property(property: 'question_group_id', type: 'integer'),
                        new OA\Property(property: 'category_id', type: 'integer'),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function importExamView(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:51200', // 50MB max
            'exam_id' => 'nullable|exists:exams,id',
            'question_group_id' => 'nullable|exists:question_groups,id',
            'category_id' => 'nullable|exists:question_categories,id',
        ]);

        $file = $request->file('file');
        $examId = $request->exam_id;
        $questionGroupId = $request->question_group_id;
        $categoryId = $request->category_id;

        $result = $this->questionBankService->importExamViewZip($file, $examId, $questionGroupId, $categoryId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Download Excel template for importing questions
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/question-bank/download-template',
        summary: 'Download template Excel untuk import soal',
        tags: ['Question Bank'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'File Excel template berhasil diunduh',
                content: new OA\MediaType(
                    mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                )
            )
        ]
    )]
    public function downloadTemplate()
    {
        try {
            return $this->questionBankService->downloadTemplate();
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengunduh template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export question bank to Excel file
     *
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: '/question-bank/export',
        summary: 'Export bank soal',
        tags: ['Question Bank'],
        parameters: [
            new OA\Parameter(name: 'exam_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question_group_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function export(Request $request)
    {
        $request->validate([
            'exam_id' => 'nullable|exists:exams,id',
            'question_group_id' => 'nullable|exists:question_groups,id',
            'category_id' => 'nullable|exists:question_categories,id',
        ]);

        $examId = $request->exam_id;
        $questionGroupId = $request->question_group_id;
        $categoryId = $request->category_id;

        $result = $this->questionBankService->exportQuestionBank($examId, $questionGroupId, $categoryId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
