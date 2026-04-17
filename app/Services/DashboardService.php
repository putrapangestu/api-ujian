<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Question;
use App\Models\Exam;
use App\Models\UserExam;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService extends BaseService
{
    /**
     * Get dashboard summary (Top Cards)
     * Cached for 1 hour to reduce DB load
     */
    public function getSummary(): array
    {
        try {
            $data = Cache::remember('admin_dashboard_summary', 3600, function () {
                return [
                    'total_students' => Student::count(),
                    'total_questions' => Question::count(),
                    'total_exams' => Exam::count(),
                ];
            });

            return $this->successResponse($data, 'Berhasil mengambil data summary dashboard');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data summary', $e->getMessage(), 500);
        }
    }

    /**
     * Get today's active exams with participant progress
     */
    public function getActiveExams(): array
    {
        try {
            $today = Carbon::today();

            $exams = Exam::withCount([
                'userExams as total_participants',
                'userExams as participants_in_progress' => function ($query) {
                    $query->where('status', 'in_progress');
                },
                'userExams as participants_completed' => function ($query) {
                    $query->where('status', 'completed');
                },
                'userExams as participants_pending' => function ($query) {
                    $query->where('status', 'pending');
                }
            ])
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date', 'asc')
            ->get();

            return $this->successResponse($exams, 'Berhasil mengambil data ujian aktif hari ini');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data ujian aktif', $e->getMessage(), 500);
        }
    }

    /**
     * Get student registration chart data
     */
    public function getStudentChart(?int $year = null): array
    {
        try {
            $year = $year ?? Carbon::now()->year;
            $cacheKey = "admin_dashboard_student_chart_{$year}";

            $data = Cache::remember($cacheKey, 3600, function () use ($year) {
                $monthlyData = Student::select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereYear('created_at', $year)
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month')
                ->toArray();

                $result = [];
                for ($i = 1; $i <= 12; $i++) {
                    $result[] = [
                        'month' => $i,
                        'month_name' => Carbon::create()->month($i)->translatedFormat('F'),
                        'total' => isset($monthlyData[$i]) ? $monthlyData[$i]['count'] : 0,
                    ];
                }

                return [
                    'year' => $year,
                    'chart_data' => $result
                ];
            });

            return $this->successResponse($data, 'Berhasil mengambil data chart siswa');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data chart siswa', $e->getMessage(), 500);
        }
    }

    /**
     * Get exam participation chart data
     */
    public function getExamChart(?int $year = null): array
    {
        try {
            $year = $year ?? Carbon::now()->year;
            $cacheKey = "admin_dashboard_exam_chart_{$year}";

            $data = Cache::remember($cacheKey, 3600, function () use ($year) {
                // Participation per month
                $monthlyData = UserExam::select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->whereYear('created_at', $year)
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month')
                ->toArray();

                $trend = [];
                for ($i = 1; $i <= 12; $i++) {
                    $trend[] = [
                        'month' => $i,
                        'month_name' => Carbon::create()->month($i)->translatedFormat('F'),
                        'total_participants' => isset($monthlyData[$i]) ? $monthlyData[$i]['count'] : 0,
                    ];
                }

                // Top 5 Exams by participation
                $topExams = Exam::withCount('userExams as total_participants')
                    ->orderByDesc('total_participants')
                    ->limit(5)
                    ->get(['id', 'title']);

                return [
                    'year' => $year,
                    'trend_chart' => $trend,
                    'top_exams' => $topExams
                ];
            });

            return $this->successResponse($data, 'Berhasil mengambil data chart ujian');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data chart ujian', $e->getMessage(), 500);
        }
    }
}
