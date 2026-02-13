<?php

namespace App\Jobs;

use App\Models\UserExam;
use App\Models\ExamMonitoring;
use App\Models\UserAnswer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Process Exam Submission Job
 * 
 * Job ini memproses submit ujian secara asynchronous
 * Untuk menghindari blocking thread utama dan mengurangi beban websocket
 * 
 * @package App\Jobs
 * @author Development Team
 * @version 1.0.0
 */
class ProcessExamSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    protected $userExamId;

    /**
     * @var array
     */
    protected $answers;

    /**
     * Create a new job instance.
     *
     * @param int $userExamId
     * @param array $answers
     */
    public function __construct(int $userExamId, array $answers)
    {
        $this->userExamId = $userExamId;
        $this->answers = $answers;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        DB::transaction(function () {
            // Get user exam
            $userExam = UserExam::find($this->userExamId);
            
            if (!$userExam) {
                \Log::error('UserExam not found: ' . $this->userExamId);
                return;
            }

            // Check if already completed
            if ($userExam->status === UserExam::STATUS_COMPLETED) {
                \Log::info('UserExam already completed: ' . $this->userExamId);
                return;
            }

            // Process each answer
            $totalScore = 0;
            $correctAnswers = 0;

            foreach ($this->answers as $answerData) {
                $questionId = $answerData['question_id'];
                $answer = $answerData['answer'];
                $timeSpent = $answerData['time_spent'] ?? 0;

                // Get question
                $question = $userExam->exam->questions()->find($questionId);
                if (!$question) {
                    \Log::warning('Question not found: ' . $questionId);
                    continue;
                }

                // Check if answer is correct
                $isCorrect = $question->isCorrect($answer);
                $score = $isCorrect ? $question->score : 0;

                // Create user answer
                UserAnswer::create([
                    'user_exam_id' => $this->userExamId,
                    'question_id' => $questionId,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]);

                // Record monitoring data
                ExamMonitoring::create([
                    'user_exam_id' => $this->userExamId,
                    'student_id' => $userExam->student_id,
                    'exam_id' => $userExam->exam_id,
                    'action' => ExamMonitoring::ACTION_ANSWER,
                    'question_id' => $questionId,
                    'answer' => $answer,
                    'time_spent' => $timeSpent,
                ]);

                $totalScore += $score;
                if ($isCorrect) {
                    $correctAnswers++;
                }
            }

            // Update user exam
            $userExam->update([
                'end_time' => now(),
                'score' => $totalScore,
                'status' => UserExam::STATUS_COMPLETED,
            ]);

            // Record submission monitoring
            ExamMonitoring::create([
                'user_exam_id' => $this->userExamId,
                'student_id' => $userExam->student_id,
                'exam_id' => $userExam->exam_id,
                'action' => ExamMonitoring::ACTION_SUBMIT,
                'time_spent' => 0,
            ]);

            // Broadcast completion event
            $this->broadcastCompletion($userExam, $totalScore, $correctAnswers);

            \Log::info('Exam submission processed successfully', [
                'user_exam_id' => $this->userExamId,
                'score' => $totalScore,
                'correct_answers' => $correctAnswers,
            ]);
        });
    }

    /**
     * Handle job failure
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Exam submission job failed', [
            'user_exam_id' => $this->userExamId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Broadcast completion event
     * 
     * @param UserExam $userExam
     * @param int $totalScore
     * @param int $correctAnswers
     * @return void
     */
    private function broadcastCompletion(UserExam $userExam, int $totalScore, int $correctAnswers): void
    {
        // Prepare broadcast data
        $broadcastData = [
            'type' => 'exam_completed',
            'data' => [
                'user_exam_id' => $userExam->id,
                'student_id' => $userExam->student_id,
                'exam_id' => $userExam->exam_id,
                'score' => $totalScore,
                'correct_answers' => $correctAnswers,
                'total_questions' => $userExam->exam->total_questions,
                'passed' => $userExam->isPassed(),
                'completed_at' => $userExam->end_time->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
        ];

        // Log the broadcast (will be replaced with actual Socket.IO broadcast)
        \Log::info('Broadcasting exam completion', $broadcastData);
    }
}