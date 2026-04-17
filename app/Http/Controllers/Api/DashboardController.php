<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get Top Cards / Summary
     */
    public function getSummary()
    {
        $result = $this->dashboardService->getSummary();
        
        return response()->json($result, $result['code'] ?? 200);
    }

    /**
     * Get Today's Active Exams and Participant Status
     */
    public function getActiveExams()
    {
        $result = $this->dashboardService->getActiveExams();
        
        return response()->json($result, $result['code'] ?? 200);
    }

    /**
     * Get Student Chart (Registration Trend)
     */
    public function getStudentChart(Request $request)
    {
        $year = $request->get('year');
        $result = $this->dashboardService->getStudentChart($year ? (int) $year : null);
        
        return response()->json($result, $result['code'] ?? 200);
    }

    /**
     * Get Exam Chart (Participation Trend & Top Exams)
     */
    public function getExamChart(Request $request)
    {
        $year = $request->get('year');
        $result = $this->dashboardService->getExamChart($year ? (int) $year : null);
        
        return response()->json($result, $result['code'] ?? 200);
    }
}
