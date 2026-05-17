<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ExamMonitoringService;
use Illuminate\Support\Facades\Log;

class ExamSyncController extends Controller
{
    /**
     * Menerima sinkronisasi data batch (jawaban, monitoring, kecurangan) dari Node.js
     *
     * @param Request $request
     * @param ExamMonitoringService $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncAnswers(Request $request, ExamMonitoringService $service)
    {
        $data = $request->validate([
            'answers' => 'array',
            'monitorings' => 'array',
            'cheat_counters' => 'array',
        ]);

        try {
            $service->processBulkSync($data['answers'] ?? [], $data['monitorings'] ?? [], $data['cheat_counters'] ?? []);
            return response()->json(['status' => 'success', 'message' => 'Data tersinkronisasi']);
        } catch (\Exception $e) {
            Log::error('ExamSyncController Error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal melakukan sinkronisasi data: ' . $e->getMessage()], 500);
        }
    }
}
