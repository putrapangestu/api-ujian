<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserExam;
use App\Services\ExamService;
use App\Services\SocketIOService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
                $exam = $userExam->exam;

                // 1. Collect existing answers dari DB (sudah batch-synced sebelumnya)
                //    Format jawaban disamakan seperti yang dikirim FE via REST manual finish
                $allQuestions = [];
                $existingAnswers = \App\Models\UserAnswer::where('user_exam_id', $userExam->id)->get();
                foreach ($existingAnswers as $ea) {
                    $item = ['question_id' => $ea->question_id];
                    if ($ea->question_option_id !== null) {
                        $item['question_option_id'] = $ea->question_option_id;
                    }
                    if ($ea->answer !== null) {
                        $item['answer'] = $ea->answer;
                    }
                    if ($ea->time_spent !== null) {
                        $item['time_spent'] = $ea->time_spent;
                    }
                    $allQuestions[$ea->question_id] = $item;
                }

                // 2. Flush pending answers dari Redis (overwrite jika duplicate)
                //    Antisipasi jawaban yang belum sempat batch sync 30s dari Node.js
                $redisKey = "exam:{$userExam->exam_id}:answers:{$userExam->id}";
                $pendingAnswers = Redis::hgetall($redisKey);
                $pendingCount = 0;

                if ($pendingAnswers) {
                    foreach ($pendingAnswers as $questionId => $answerJson) {
                        $answerData = json_decode($answerJson, true);
                        $item = [
                            'question_id' => $questionId,
                            'time_spent' => $answerData['time_spent'] ?? 0,
                        ];
                        // Single/multiple choice
                        if (!empty($answerData['question_option_id'])) {
                            $item['question_option_id'] = $answerData['question_option_id'];
                        }
                        // Essay
                        if (!empty($answerData['answer'])) {
                            $item['answer'] = $answerData['answer'];
                        }
                        $allQuestions[$questionId] = $item;
                        $pendingCount++;
                    }
                    // Hapus dari Redis agar batch sync tidak duplikasi
                    Redis::del($redisKey);
                }

                // 3. Finish exam dengan SEMUA answers (DB + Redis)
                //    Identik dengan apa yang dikirim FE saat manual finish via REST
                $questions = array_values($allQuestions);
                $result = $examService->finishExam($userExam->exam_id, $userExam->user_id, $questions);

                // 3. Hitung timeRemaining = 0 (sudah habis)
                $durationInSeconds = $exam->duration * 60;
                $timeSpent = Carbon::parse($userExam->start_time)->diffInSeconds(now());
                $timeRemaining = max(0, $durationInSeconds - $timeSpent);

                // 3. Kirim event force_submit ke student (untuk redirect halaman user)
                $socketService->emitEvent(
                    'admin-action',
                    'force_submit',
                    [
                        'action' => 'exam_auto_submitted',
                        'message' => 'Waktu ujian telah habis. Ujian otomatis diselesaikan oleh sistem.',
                        'user_exam_id' => $userExam->id,
                    ],
                    "student:{$userExam->student_id}"
                );

                // 4. Kirim progress_update ke exam room (untuk update CMS monitoring real-time)
                $socketService->emitEvent(
                    "exam:{$userExam->exam_id}",
                    'exam:progress_update',
                    [
                        'id' => $userExam->student_id,
                        'update' => [
                            'timeRemaining' => $timeRemaining,
                            'status' => 'finished',
                            'totalScore' => $result['data']['summary']['total_score'] ?? $userExam->score ?? 0,
                        ]
                    ],
                    "exam:{$userExam->exam_id}"
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
