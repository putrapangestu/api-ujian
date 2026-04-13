<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserExam;
use App\Services\ExamService;
use App\Services\SocketIOService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoSubmitExams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:auto-submit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically finish exams for users whose time has expired (grace period: 60s)';

    /**
     * Execute the console command.
     */
    public function handle(ExamService $examService, SocketIOService $socketService)
    {
        $this->info('Checking for expired exams...');

        // Find user_exams that are in_progress
        $inProgressExams = UserExam::where('status', UserExam::STATUS_IN_PROGRESS)
            ->whereNotNull('start_time')
            ->with('exam')
            ->get();

        $expiredExams = $inProgressExams->filter(function ($userExam) {
            if (!$userExam->exam || !$userExam->exam->duration) {
                return false;
            }

            // Expected end time = start time + duration (in minutes)
            $expectedEndTime = Carbon::parse($userExam->start_time)->addMinutes($userExam->exam->duration);

            // Allow a grace period of 60 seconds for the final bulk sync from Node.js to complete
            return Carbon::now()->gt($expectedEndTime->addSeconds(60));
        });

        if ($expiredExams->isEmpty()) {
            $this->info('No expired exams found.');
            return;
        }

        $this->info('Found ' . $expiredExams->count() . ' expired exams. Submitting...');

        $successCount = 0;
        $failCount = 0;

        foreach ($expiredExams as $userExam) {
            try {
                // Call finishExam with an empty array. It will calculate score based on synced user_answers
                $examService->finishExam($userExam->exam_id, $userExam->user_id, []);

                // Notify via socket to force frontend redirect (if they reconnect or are lingering)
                $socketService->emitEvent(
                    'admin-action',
                    'force_submit',
                    ['message' => 'Waktu ujian telah habis. Ujian otomatis diselesaikan oleh sistem.'],
                    "student:{$userExam->student_id}"
                );

                $successCount++;
                $this->line("Auto-submitted user_exam_id: {$userExam->id} (Student ID: {$userExam->student_id})");
            } catch (\Exception $e) {
                Log::error("Failed to auto-submit exam {$userExam->id}: " . $e->getMessage());
                $failCount++;
                $this->error("Failed user_exam_id: {$userExam->id}");
            }
        }

        $this->info("Completed! Success: {$successCount}, Failed: {$failCount}");
    }
}
