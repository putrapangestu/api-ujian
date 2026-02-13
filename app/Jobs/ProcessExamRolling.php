<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\Student;
use App\Models\UserExam;
use App\Models\UserAnswer;
use App\Models\QuestionExam;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessExamRolling Job
 * 
 * Job ini menangani rolling student yang mengikuti ujian
 * Membuat user_exam dan user_answer untuk setiap student dengan soal yang random
 * 
 * @package App\Jobs
 * @author Development Team
 * @version 1.0.0
 */
class ProcessExamRolling implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The exam instance.
     *
     * @var \App\Models\Exam
     */
    protected $exam;

    /**
     * The student instance.
     *
     * @var \App\Models\Student
     */
    protected $student;

    /**
     * Create a new job instance.
     *
     * @param \App\Models\Exam $exam
     * @param \App\Models\Student $student
     */
    public function __construct(Exam $exam, Student $student)
    {
        $this->exam = $exam;
        $this->student = $student;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::transaction(function () {
                // Check if student already has user_exam for this exam
                $existingUserExam = UserExam::where('exam_id', $this->exam->id)
                    ->where('student_id', $this->student->id)
                    ->first();

                if ($existingUserExam) {
                    Log::info('Student already has user_exam for this exam', [
                        'exam_id' => $this->exam->id,
                        'student_id' => $this->student->id,
                        'user_exam_id' => $existingUserExam->id,
                    ]);
                    return;
                }

                // Create user_exam
                $userExam = UserExam::create([
                    'user_id' => $this->student->user_id,
                    'exam_id' => $this->exam->id,
                    'student_id' => $this->student->id,
                    'status' => UserExam::STATUS_PENDING,
                    'score' => 0,
                    'start_time' => null,
                    'end_time' => null,
                ]);

                Log::info('User exam created', [
                    'user_exam_id' => $userExam->id,
                    'exam_id' => $this->exam->id,
                    'student_id' => $this->student->id,
                ]);

                // Get all questions for this exam
                $examQuestions = QuestionExam::where('exam_id', $this->exam->id)
                    ->where('is_active', true)
                    ->with(['question', 'questionGroup'])
                    ->get();

                if ($examQuestions->isEmpty()) {
                    throw new \Exception('No questions found for this exam');
                }

                Log::info('Found exam questions', [
                    'exam_id' => $this->exam->id,
                    'question_count' => $examQuestions->count(),
                ]);

                // Generate random order for this student
                $shuffledQuestions = $examQuestions->shuffle();

                // Create user_answer entries with random order
                foreach ($shuffledQuestions as $index => $examQuestion) {
                    $question = $examQuestion->question;

                    // Create user_answer with random order
                    UserAnswer::create([
                        'user_exam_id' => $userExam->id,
                        'question_id' => $question->id,
                        'question_option_id' => null, // Will be filled when student answers
                        'answer' => null,
                        'is_correct' => false,
                        'score' => 0,
                        'time_spent' => 0,
                        'question_order' => $index + 1, // Random order (1, 2, 3, etc.)
                        'question_number' => $examQuestion->question_number, // Original question number
                    ]);

                    Log::debug('User answer created', [
                        'user_exam_id' => $userExam->id,
                        'question_id' => $question->id,
                        'question_order' => $index + 1,
                        'question_number' => $examQuestion->question_number,
                    ]);
                }

                Log::info('Exam rolling completed successfully', [
                    'exam_id' => $this->exam->id,
                    'student_id' => $this->student->id,
                    'user_exam_id' => $userExam->id,
                    'total_questions' => $examQuestions->count(),
                ]);

            });

        } catch (\Exception $e) {
            Log::error('Error in exam rolling job', [
                'exam_id' => $this->exam->id,
                'student_id' => $this->student->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Exam rolling job failed', [
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}