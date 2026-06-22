<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExamMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;

/**
 * ExamMonitoring Controller
 *
 * Controller ini menangani monitoring realtime pengerjaan ujian
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class ExamMonitoringController extends Controller
{
    /**
     * @var ExamMonitoringService
     */
    protected ExamMonitoringService $examMonitoringService;

    /**
     * ExamMonitoringController constructor
     *
     * @param ExamMonitoringService $examMonitoringService
     */
    public function __construct(ExamMonitoringService $examMonitoringService)
    {
        $this->examMonitoringService = $examMonitoringService;
    }

    /**
     * Get list of active (ongoing) exams for monitoring
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/active",
        summary: "Daftar ujian yang sedang berlangsung",
        tags: ["Exam Monitoring"],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getActiveExams(Request $request)
    {
        $filters = $request->only(['search']);
        $perPage = (int) $request->get('per_page', 10);

        $result = $this->examMonitoringService->getActiveExams($filters, $perPage);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Record exam action for monitoring
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/exam-monitoring/record-action",
        summary: "Catat aksi ujian",
        tags: ["Exam Monitoring"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_exam_id", type: "integer"),
                    new OA\Property(property: "student_id", type: "integer"),
                    new OA\Property(property: "exam_id", type: "integer"),
                    new OA\Property(property: "action", type: "string", enum: ["start","answer","navigate","submit","pause","resume"]),
                    new OA\Property(property: "question_id", type: "integer"),
                    new OA\Property(property: "answer", type: "string"),
                    new OA\Property(property: "time_spent", type: "integer", minimum: 0),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function recordAction(Request $request)
    {
        $request->validate([
            'user_exam_id' => 'required|exists:user_exams,id',
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'action' => 'required|in:start,answer,navigate,submit,pause,resume',
            'question_id' => 'nullable|exists:questions,id',
            'answer' => 'nullable|string',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $result = $this->examMonitoringService->recordAction($request->all());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], $result['code'] ?? 200);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get real-time exam monitoring data
     *
     * @param Request $request
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/realtime/{examId}",
        summary: "Monitoring realtime ujian",
        tags: ["Exam Monitoring"],
        parameters: [new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getRealtimeMonitoring(Request $request, $examId)
    {
        $result = $this->examMonitoringService->getRealtimeMonitoring($examId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get detailed monitoring for specific student
     *
     * @param Request $request
     * @param string $studentId
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/student/{studentId}/{examId}",
        summary: "Monitoring detail siswa",
        tags: ["Exam Monitoring"],
        parameters: [
            new OA\Parameter(name: "studentId", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getStudentMonitoring(Request $request, $studentId, $examId)
    {
        $result = $this->examMonitoringService->getStudentMonitoring($studentId, $examId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get exam summary for monitoring dashboard
     *
     * @param Request $request
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/summary/{examId}",
        summary: "Ringkasan monitoring ujian",
        tags: ["Exam Monitoring"],
        parameters: [new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getExamSummary(Request $request, $examId)
    {
        $result = $this->examMonitoringService->getExamSummary($examId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get monitoring by question group
     *
     * @param Request $request
     * @param string $examId
     * @param string $questionGroupId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/by-group/{examId}/{questionGroupId}",
        summary: "Monitoring per grup soal",
        tags: ["Exam Monitoring"],
        parameters: [
            new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "questionGroupId", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getMonitoringByQuestionGroup(Request $request, $examId, $questionGroupId)
    {
        $result = $this->examMonitoringService->getMonitoringByQuestionGroup($examId, $questionGroupId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Get monitoring by category
     *
     * @param Request $request
     * @param string $examId
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Get(
        path: "/exam-monitoring/by-category/{examId}",
        summary: "Monitoring per kategori",
        tags: ["Exam Monitoring"],
        parameters: [new OA\Parameter(name: "examId", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function getMonitoringByCategory(Request $request, $examId)
    {
        $result = $this->examMonitoringService->getMonitoringByCategory($examId);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    /**
     * Force submit exam by admin
     *
     * @param Request $request
     * @param \App\Services\ExamService $examService
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/exam-monitoring/force-submit",
        summary: "Force submit ujian oleh admin",
        tags: ["Exam Monitoring"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_exam_id", type: "integer"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function forceSubmit(Request $request, \App\Services\ExamService $examService)
    {
        $request->validate([
            'user_exam_id' => 'required|exists:user_exams,id'
        ]);

        $userExam = \App\Models\UserExam::find($request->user_exam_id);

        // Panggil method finishExam dari ExamService dengan parameter yang benar
        $result = $examService->finishExam($userExam->exam_id, $userExam->user_id, []);

        // Cek hasil sebelum mengembalikan response sukses
        if (!$result['success']) {
            return $this->errorResponse($result['message'] ?? 'Gagal menghentikan ujian', 400);
        }

        // Hapus data Redis agar tidak stale
        $this->cleanupRedisState($userExam);

        // Broadcast force submit ke student via socket
        app(\App\Services\SocketIOService::class)->emitEvent(
            'admin-action',
            'force_submit',
            ['action' => 'force_submit', 'message' => 'Ujian Anda dihentikan paksa oleh Admin.'],
            "student:{$userExam->student_id}"
        );

        return $this->successResponse(null, 'Peserta dihentikan paksa.');
    }

    /**
     * Unblock student exam
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/exam-monitoring/unblock",
        summary: "Buka blokir ujian siswa",
        tags: ["Exam Monitoring"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_exam_id", type: "integer"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function unblockStudent(Request $request)
    {
        $request->validate([
            'user_exam_id' => 'required|exists:user_exams,id'
        ]);

        $userExam = \App\Models\UserExam::find($request->user_exam_id);

        app(\App\Services\SocketIOService::class)->emitEvent(
            'admin-action',
            'unblock',
            ['action' => 'unblock', 'message' => 'Silakan lanjutkan ujian.'],
            "student:{$userExam->student_id}"
        );

        return $this->successResponse(null, 'Blokir dibuka.');
    }

    /**
     * Reset student exam
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/exam-monitoring/reset",
        summary: "Reset ujian siswa",
        tags: ["Exam Monitoring"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user_exam_id", type: "integer"),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Success")]
    )]
    public function resetExam(Request $request)
    {
        $request->validate([
            'user_exam_id' => 'required|exists:user_exams,id'
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $userExam = \App\Models\UserExam::find($request->user_exam_id);

            // Simpan data untuk broadcast sebelum dihapus
            $studentId = $userExam->student_id;
            $examId = $userExam->exam_id;

            // Hapus jawaban
            \App\Models\UserAnswer::where('user_exam_id', $userExam->id)->delete();
            
            // Hapus log monitoring
            \App\Models\ExamMonitoring::where('user_exam_id', $userExam->id)->delete();

            // Update user exam kembali ke pending atau hapus untuk re-roll
            // Di sini kita reset status dan score agar bisa mulai lagi
            $userExam->update([
                'status' => \App\Models\UserExam::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'score' => 0
            ]);

            // Hapus data Redis agar tidak stale
            $this->cleanupRedisState($userExam);

            // Broadcast reset ke student via socket jika sedang online
            app(\App\Services\SocketIOService::class)->emitEvent(
                'admin-action',
                'reset',
                ['action' => 'exam_reset', 'message' => 'Ujian Anda telah direset oleh Admin.'],
                "student:{$studentId}"
            );

            return $this->successResponse(null, 'Ujian berhasil direset.');
        });
    }

    /**
     * Hapus data Redis yang berkaitan dengan user_exam.
     * Mencegah data stale jika student reconnect setelah di-force submit / reset.
     */
    private function cleanupRedisState(\App\Models\UserExam $userExam): void
    {
        try {
            $examId = $userExam->exam_id;
            $userExamId = $userExam->id;
            $studentId = $userExam->student_id;

            // Hapus state participant di hash exam:{examId}:state
            Redis::hdel("exam:{$examId}:state", $userExamId);

            // Hapus jawaban cache di exam:{examId}:answers:{userExamId}
            Redis::del("exam:{$examId}:answers:{$userExamId}");

            // Hapus reverse mapping student_id → user_exam_id
            Redis::del("exam_map:{$studentId}:{$examId}");

            // Hapus dari set answer-keys
            Redis::srem('exam:answer-keys', "exam:{$examId}:answers:{$userExamId}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal cleanup Redis: ' . $e->getMessage());
        }
    }
}
