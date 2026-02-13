<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API Testing Command
 * 
 * Command ini untuk testing semua API endpoints secara menyeluruh
 * 
 * @package App\Console\Commands
 * @author Development Team
 * @version 1.0.0
 */
class TestAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:test {--host=localhost:8000} {--detailed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testing semua API endpoints secara menyeluruh';

    /**
     * Base URL untuk testing
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Token autentikasi
     *
     * @var string|null
     */
    protected $token = null;

    /**
     * Verbose mode
     *
     * @var bool
     */
    protected $detailed = false;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->baseUrl = 'http://' . $this->option('host');
        $this->detailed = $this->option('detailed');

        $this->info('Starting API Testing...');
        $this->info('Base URL: ' . $this->baseUrl);

        try {
            // Test Authentication
            $this->testAuthentication();

            if ($this->token) {
                // Test Question Groups
                $this->testQuestionGroups();

                // Test Question Categories
                $this->testQuestionCategories();

                // Test Questions
                $this->testQuestions();

                // Test Exams
                $this->testExams();

                // Test Exam Question Selection
                $this->testExamQuestionSelection();

                // Test Exam Rolling
                $this->testExamRolling();

                // Test Student Groups
                $this->testStudentGroups();

                // Test Students
                $this->testStudents();

                // Test Exam Monitoring
                $this->testExamMonitoring();

                $this->info('✅ All API tests completed successfully!');
            } else {
                $this->error('❌ Authentication failed. Cannot proceed with other tests.');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ API testing failed: ' . $e->getMessage());
            Log::error('API testing failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    /**
     * Test authentication endpoints
     */
    private function testAuthentication()
    {
        $this->info('Testing Authentication...');

        // Test Register
        $timestamp = time();
        $response = Http::post($this->baseUrl . '/api/auth/register', [
            'name' => 'Test User ' . $timestamp,
            'email' => 'test' . $timestamp . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        if ($response->successful()) {
            $this->info('✅ Register endpoint working');
        } else {
            $this->warn('⚠️ Register endpoint failed: ' . $response->body());
        }

        // Test Login
        $response = Http::post($this->baseUrl . '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->token = $data['data']['token'] ?? null;
            $this->info('✅ Login endpoint working');
            $this->info('Token obtained: ' . substr($this->token, 0, 20) . '...');
        } else {
            $this->error('❌ Login endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test question groups endpoints
     */
    private function testQuestionGroups()
    {
        $this->info('Testing Question Groups...');

        // Test Get All Question Groups
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->baseUrl . '/api/question-groups');

        if ($response->successful()) {
            $this->info('✅ Get question groups endpoint working');
            $data = $response->json();
            if ($this->detailed) {
                $this->line('Total groups: ' . count($data['data'] ?? []));
            }
        } else {
            $this->error('❌ Get question groups endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test question categories endpoints
     */
    private function testQuestionCategories()
    {
        $this->info('Testing Question Categories...');

        // Test Get All Question Categories
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->baseUrl . '/api/question-categories');

        if ($response->successful()) {
            $this->info('✅ Get question categories endpoint working');
        } else {
            $this->error('❌ Get question categories endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test questions endpoints
     */
    private function testQuestions()
    {
        $this->info('Testing Questions...');

        // Test Get All Questions
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->baseUrl . '/api/questions');

        if ($response->successful()) {
            $this->info('✅ Get questions endpoint working');
        } else {
            $this->error('❌ Get questions endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test exams endpoints
     */
    private function testExams()
    {
        $this->info('Testing Exams...');

        // Test Get All Exams
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->baseUrl . '/api/exams');

        if ($response->successful()) {
            $this->info('✅ Get exams endpoint working');
            $data = $response->json();
            
            if (isset($data['data']) && count($data['data']) > 0) {
                $examId = $data['data'][0]['id'] ?? null;
                if ($examId) {
                    // Test Get Single Exam
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                    ])->get($this->baseUrl . '/api/exams/' . $examId);

                    if ($response->successful()) {
                        $this->info('✅ Get single exam endpoint working');
                    } else {
                        $this->error('❌ Get single exam endpoint failed: ' . $response->body());
                    }
                }
            }
        } else {
            $this->error('❌ Get exams endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test exam question selection endpoints
     */
    private function testExamQuestionSelection()
    {
        $this->info('Testing Exam Question Selection...');

        // Test Validate Question Banks
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->post($this->baseUrl . '/api/exam-questions/validate-banks', [
            'question_banks' => [
                [
                    'question_group_id' => 1,
                    'question_count' => 5,
                    'random_selection' => true,
                ],
            ],
        ]);

        if ($response->successful()) {
            $this->info('✅ Validate question banks endpoint working');
        } else {
            $this->error('❌ Validate question banks endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test exam rolling endpoints
     */
    private function testExamRolling()
    {
        $this->info('Testing Exam Rolling...');

        // Test Get Rollable Students
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->baseUrl . '/api/exam-rolling/rollable-students/1');

        if ($response->successful()) {
            $this->info('✅ Get rollable students endpoint working');
        } else {
            $this->error('❌ Get rollable students endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test student groups endpoints
     */
    private function testStudentGroups()
    {
        $this->info('Testing Student Groups...');

        // Test Get All Student Groups
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->baseUrl . '/api/student-groups');

        if ($response->successful()) {
            $this->info('✅ Get student groups endpoint working');
        } else {
            $this->error('❌ Get student groups endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test students endpoints
     */
    private function testStudents()
    {
        $this->info('Testing Students...');

        // Test Get All Students
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->baseUrl . '/api/students');

        if ($response->successful()) {
            $this->info('✅ Get students endpoint working');
        } else {
            $this->error('❌ Get students endpoint failed: ' . $response->body());
        }
    }

    /**
     * Test exam monitoring endpoints
     */
    private function testExamMonitoring()
    {
        $this->info('Testing Exam Monitoring...');

        // Test Get Exam Monitoring Summary
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->get($this->baseUrl . '/api/exam-monitoring/summary/1');

        if ($response->successful()) {
            $this->info('✅ Get exam monitoring summary endpoint working');
        } else {
            $this->error('❌ Get exam monitoring summary endpoint failed: ' . $response->body());
        }
    }
}