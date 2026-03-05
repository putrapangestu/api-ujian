<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exam;
use App\Models\Student;
use App\Models\UserExam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\UserAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performance Test Command
 * 
 * Command ini untuk testing performance dengan 1000 user mengikuti ujian
 * 
 * @package App\Console\Commands
 * @author Development Team
 * @version 1.0.0
 */
class PerformanceTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:performance {--users=1000 : Number of users to test} {--questions=50 : Number of questions per exam}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test API performance with multiple users taking exam simultaneously';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userCount = $this->option('users');
        $questionCount = $this->option('questions');

        $this->info("Starting performance test with {$userCount} users and {$questionCount} questions...");

        // Record start time
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            // Create test data
            $this->info('Creating test data...');
            $exam = $this->createTestExam($questionCount);
            $students = $this->createTestStudents($userCount);

            // Simulate concurrent exam taking
            $this->info('Simulating concurrent exam taking...');
            $results = $this->simulateConcurrentExamTaking($exam, $students, $questionCount);

            // Record end time
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            // Calculate metrics
            $totalTime = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            $avgTimePerUser = $totalTime / $userCount;
            $requestsPerSecond = $userCount / $totalTime;

            // Display results
            $this->displayResults([
                'total_users' => $userCount,
                'total_questions' => $questionCount,
                'total_time' => round($totalTime, 2),
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'avg_time_per_user' => round($avgTimePerUser, 4),
                'requests_per_second' => round($requestsPerSecond, 2),
                'successful_submissions' => $results['successful'],
                'failed_submissions' => $results['failed'],
                'average_score' => round($results['average_score'], 2),
                'max_score' => $results['max_score'],
                'min_score' => $results['min_score'],
            ]);

            // Log detailed results
            $this->logDetailedResults($results);

            $this->info('Performance test completed successfully!');

        } catch (\Exception $e) {
            $this->error('Performance test failed: ' . $e->getMessage());
            Log::error('Performance test error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Create test exam with questions
     * 
     * @param int $questionCount
     * @return Exam
     */
    private function createTestExam(int $questionCount): Exam
    {
        return DB::transaction(function () use ($questionCount) {
            // Create exam
            $exam = Exam::create([
                'title' => 'Performance Test Exam',
                'description' => 'This is a test exam for performance testing',
                'duration' => 60,
                'total_questions' => $questionCount,
                'passing_score' => 70,
                'start_date' => now(),
                'end_date' => now()->addHours(2),
            ]);

            // Create questions
            for ($i = 1; $i <= $questionCount; $i++) {
                $question = Question::create([
                    'exam_id' => $exam->id,
                    'question_text' => "Question {$i}: What is 2 + 2?",
                    'question_type' => 'multiple_choice',
                    'score' => 2,
                    'correct_answer' => '4',
                ]);

                // Create options
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => '3',
                    'is_correct' => false,
                    'score' => 0,
                    'option_label' => 'A',
                ]);

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => '4',
                    'is_correct' => true,
                    'score' => 2,
                    'option_label' => 'B',
                ]);

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => '5',
                    'is_correct' => false,
                    'score' => 0,
                    'option_label' => 'C',
                ]);

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => '6',
                    'is_correct' => false,
                    'score' => 0,
                    'option_label' => 'D',
                ]);
            }

            return $exam;
        });
    }

    /**
     * Create test students
     * 
     * @param int $count
     * @return \Illuminate\Support\Collection
     */
    private function createTestStudents(int $count)
    {
        $students = collect();

        for ($i = 1; $i <= $count; $i++) {
            $student = Student::create([
                'nisn' => 'TEST' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'name' => 'Test Student ' . $i,
                'email' => 'test' . $i . '@example.com',
                'group_id' => 1, // Default group
                'status' => 'active',
            ]);

            $students->push($student);
        }

        return $students;
    }

    /**
     * Simulate concurrent exam taking
     * 
     * @param Exam $exam
     * @param \Illuminate\Support\Collection $students
     * @param int $questionCount
     * @return array
     */
    private function simulateConcurrentExamTaking(Exam $exam, $students, int $questionCount): array
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'scores' => [],
            'average_score' => 0,
            'max_score' => 0,
            'min_score' => 0,
        ];

        $this->info('Creating user exams...');
        $progressBar = $this->output->createProgressBar($students->count());
        $progressBar->start();

        // Create user exams
        $userExams = collect();
        foreach ($students as $student) {
            $userExam = UserExam::create([
                'user_id' => $student->id,
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'start_time' => now(),
                'status' => 'in_progress',
            ]);
            $userExams->push($userExam);
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->newLine();

        $this->info('Simulating exam submissions...');
        $progressBar = $this->output->createProgressBar($userExams->count());
        $progressBar->start();

        // Simulate submissions
        foreach ($userExams as $userExam) {
            try {
                $score = $this->simulateExamSubmission($userExam, $exam, $questionCount);
                $results['scores'][] = $score;
                $results['successful']++;
            } catch (\Exception $e) {
                $results['failed']++;
                Log::warning('Exam submission failed for user ' . $userExam->user_id . ': ' . $e->getMessage());
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->newLine();

        // Calculate statistics
        if (!empty($results['scores'])) {
            $results['average_score'] = array_sum($results['scores']) / count($results['scores']);
            $results['max_score'] = max($results['scores']);
            $results['min_score'] = min($results['scores']);
        }

        return $results;
    }

    /**
     * Simulate exam submission for a single user
     * 
     * @param UserExam $userExam
     * @param Exam $exam
     * @param int $questionCount
     * @return float
     */
    private function simulateExamSubmission(UserExam $userExam, Exam $exam, int $questionCount): float
    {
        return DB::transaction(function () use ($userExam, $exam, $questionCount) {
            $totalScore = 0;
            $questions = $exam->questions()->inRandomOrder()->limit($questionCount)->get();

            foreach ($questions as $question) {
                // Simulate random answers (80% chance of correct answer)
                $isCorrect = rand(1, 100) <= 80;
                
                if ($isCorrect) {
                    $correctOption = $question->questionOptions()->where('is_correct', true)->first();
                    $answer = $correctOption ? $correctOption->option_text : '4';
                    $score = $question->score;
                } else {
                    $wrongOptions = $question->questionOptions()->where('is_correct', false)->get();
                    $wrongOption = $wrongOptions->random();
                    $answer = $wrongOption->option_text;
                    $score = 0;
                }

                UserAnswer::create([
                    'user_exam_id' => $userExam->id,
                    'question_id' => $question->id,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]);

                $totalScore += $score;
            }

            // Update user exam
            $userExam->update([
                'end_time' => now(),
                'score' => $totalScore,
                'status' => 'completed',
            ]);

            return $totalScore;
        });
    }

    /**
     * Display test results
     * 
     * @param array $results
     * @return void
     */
    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('=== PERFORMANCE TEST RESULTS ===');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Users', $results['total_users']],
                ['Total Questions', $results['total_questions']],
                ['Total Time (seconds)', $results['total_time']],
                ['Memory Used (MB)', $results['memory_used_mb']],
                ['Avg Time per User (seconds)', $results['avg_time_per_user']],
                ['Requests per Second', $results['requests_per_second']],
                ['Successful Submissions', $results['successful_submissions']],
                ['Failed Submissions', $results['failed_submissions']],
                ['Average Score', $results['average_score']],
                ['Max Score', $results['max_score']],
                ['Min Score', $results['min_score']],
            ]
        );

        $this->newLine();

        // Performance analysis
        if ($results['avg_time_per_user'] < 0.1) {
            $this->info('✅ EXCELLENT: Average response time is very fast (< 0.1s per user)');
        } elseif ($results['avg_time_per_user'] < 0.5) {
            $this->info('✅ GOOD: Average response time is good (< 0.5s per user)');
        } elseif ($results['avg_time_per_user'] < 1) {
            $this->warn('⚠️  ACCEPTABLE: Average response time is acceptable (< 1s per user)');
        } else {
            $this->error('❌ POOR: Average response time is slow (> 1s per user)');
        }

        if ($results['requests_per_second'] > 100) {
            $this->info('✅ EXCELLENT: High throughput (> 100 requests/second)');
        } elseif ($results['requests_per_second'] > 50) {
            $this->info('✅ GOOD: Good throughput (> 50 requests/second)');
        } elseif ($results['requests_per_second'] > 20) {
            $this->warn('⚠️  ACCEPTABLE: Acceptable throughput (> 20 requests/second)');
        } else {
            $this->error('❌ POOR: Low throughput (< 20 requests/second)');
        }

        if ($results['failed_submissions'] == 0) {
            $this->info('✅ EXCELLENT: No failed submissions');
        } elseif ($results['failed_submissions'] < $results['total_users'] * 0.01) {
            $this->info('✅ GOOD: Less than 1% failed submissions');
        } elseif ($results['failed_submissions'] < $results['total_users'] * 0.05) {
            $this->warn('⚠️  ACCEPTABLE: Less than 5% failed submissions');
        } else {
            $this->error('❌ POOR: More than 5% failed submissions');
        }
    }

    /**
     * Log detailed results
     * 
     * @param array $results
     * @return void
     */
    private function logDetailedResults(array $results): void
    {
        Log::info('Performance Test Results', [
            'test_type' => 'concurrent_exam_taking',
            'total_users' => $results['total_users'],
            'total_questions' => $results['total_questions'],
            'total_time_seconds' => $results['total_time'],
            'memory_used_mb' => $results['memory_used_mb'],
            'avg_time_per_user_seconds' => $results['avg_time_per_user'],
            'requests_per_second' => $results['requests_per_second'],
            'successful_submissions' => $results['successful_submissions'],
            'failed_submissions' => $results['failed_submissions'],
            'average_score' => $results['average_score'],
            'max_score' => $results['max_score'],
            'min_score' => $results['min_score'],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
