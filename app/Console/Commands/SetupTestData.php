<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\QuestionGroup;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Exam;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Setup Test Data Command
 *
 * Command ini untuk membuat data testing untuk fitur baru
 *
 * @package App\Console\Commands
 * @author Development Team
 * @version 1.0.0
 */
class SetupTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:setup-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup test data for new features';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up test data...');

        try {
            DB::transaction(function () {
                // Create question categories
                $this->createQuestionCategories();

                // Create student group
                $this->createStudentGroup();

                // Create user for students
                $this->createUserForStudents();

                // Create students
                $this->createStudents();

                // Create question groups
                $this->createQuestionGroups();

                // Create questions
                $this->createQuestions();

                // Create exam
                $this->createExam();
            });

            $this->info('Test data setup completed successfully!');

        } catch (\Exception $e) {
            $this->error('Error setting up test data: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Create question categories
     */
    private function createQuestionCategories()
    {
        $this->info('Creating question categories...');

        $categories = [
            [
                'name' => 'Pendidikan Kewarganegaraan',
                'description' => 'Soal-soal tentang Pendidikan Kewarganegaraan',
                'code' => 'PKN',
                'status' => 'active',
            ],
            [
                'name' => 'Matematika Dasar',
                'description' => 'Soal-soal tentang Matematika Dasar',
                'code' => 'MTK',
                'status' => 'active',
            ],
            [
                'name' => 'Bahasa Indonesia',
                'description' => 'Soal-soal tentang Bahasa Indonesia',
                'code' => 'BIN',
                'status' => 'active',
            ],
        ];

        foreach ($categories as $categoryData) {
            \App\Models\QuestionCategory::create($categoryData);
        }

        $this->info('Question categories created successfully');
    }

    /**
     * Create question groups
     */
    private function createQuestionGroups()
    {
        $this->info('Creating question groups...');

        $groups = [
            [
                'name' => 'Bank Soal PKN',
                'description' => 'Bank soal Pendidikan Kewarganegaraan',
                'code' => 'PKN',
                'status' => 'active',
            ],
            [
                'name' => 'Bank Soal Matematika',
                'description' => 'Bank soal Matematika Dasar',
                'code' => 'MTK',
                'status' => 'active',
            ],
            [
                'name' => 'Bank Soal Bahasa Indonesia',
                'description' => 'Bank soal Bahasa Indonesia',
                'code' => 'BIN',
                'status' => 'active',
            ],
        ];

        foreach ($groups as $groupData) {
            QuestionGroup::create($groupData);
        }

        $this->info('Question groups created successfully');
    }

    /**
     * Create questions
     */
    private function createQuestions()
    {
        $this->info('Creating questions...');

        $questionGroups = QuestionGroup::all();

        foreach ($questionGroups as $group) {
            // Create 10 questions per group
                for ($i = 1; $i <= 10; $i++) {
                    $question = Question::create([
                        'exam_id' => null, // Bank soal questions don't need exam_id
                        'question_group_id' => $group->id,
                        'category_id' => 1, // Default category
                        'question_text' => "Soal {$i} dari {$group->name}: Apa yang dimaksud dengan...?",
                        'question_type' => 'multiple_choice',
                        'score' => 2,
                        'correct_answer' => 'Jawaban B',
                        'is_active' => true,
                    ]);

                // Create options
                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Jawaban A',
                    'is_correct' => false,
                    'score' => 0,
                ]);

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Jawaban B',
                    'is_correct' => true,
                    'score' => 2,
                ]);

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Jawaban C',
                    'is_correct' => false,
                    'score' => 0,
                ]);

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'Jawaban D',
                    'is_correct' => false,
                    'score' => 0,
                ]);
            }
        }

        $this->info('Questions created successfully');
    }

    /**
     * Create exam
     */
    private function createExam()
    {
        $this->info('Creating exam...');

        $exam = Exam::create([
            'title' => 'Ujian CPNS Tahun 2024',
            'description' => 'Ujian seleksi CPNS untuk formasi tahun 2024',
            'duration' => 120, // 2 hours
            'total_questions' => 0, // Will be updated later
            'passing_score' => 70,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $this->info('Exam created successfully: ' . $exam->title);
    }

    /**
     * Create user for students
     */
    private function createUserForStudents()
    {
        $this->info('Creating user for students...');

        $user = User::create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $this->info('User for students created successfully');

        return $user;
    }

    /**
     * Create students
     */
    private function createStudents()
    {
        $this->info('Creating students...');

        $studentGroup = StudentGroup::first();
        $user = User::where('email', 'student@example.com')->first();

        if (!$studentGroup || !$user) {
            $this->error('Student group or user not found');
            return;
        }

        // Create students
        for ($i = 1; $i <= 5; $i++) {
            Student::create([
                'nisn' => '123456789' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'name' => 'Student ' . $i,
                'email' => 'student' . $i . '@example.com',
                'phone' => '0812345678' . $i,
                'address' => 'Address ' . $i,
                'group_id' => $studentGroup->id,
                'user_id' => $user->id,
                'status' => 'active',
            ]);
        }

        $this->info('Students created successfully');
    }

    /**
     * Create student group
     */
    private function createStudentGroup()
    {
        $this->info('Creating student group...');

        StudentGroup::create([
            'name' => 'Kelas A',
            'description' => 'Kelas A - Kelas Unggulan',
            'code' => 'KLS-A',
            'max_students' => 50,
            'status' => 'active',
        ]);

        $this->info('Student group created successfully');
    }
}
