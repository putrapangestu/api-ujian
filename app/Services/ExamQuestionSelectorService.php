<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionExam;
use App\Models\Student;
use App\Models\UserExam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExamQuestionSelector Service Class
 *
 * Service ini menangani pemilihan soal dari bank soal saat membuat ujian
 * Menentukan nomor urut, range, dan urutan random untuk setiap siswa
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class ExamQuestionSelectorService extends BaseService
{
    /**
     * Select questions from question banks for exam
     *
     * @param Exam $exam
     * @param array $questionBanks Array of question bank selections
     * @return array
     */
    public function selectQuestionsForExam(Exam $exam, array $questionBanks): array
    {
        return DB::transaction(function () use ($exam, $questionBanks) {
            try {
                $selectedQuestions = [];
                $questionNumber = 1;

                $selectedQuestionsFromGroup = Question::with('category','questionGroup')
                    ->whereIn('id', $questionBanks)
                    ->whereNull('deleted_at')
                    ->get();

                // foreach ($questionBanks as $bankData) {
                //     // $randomSelection = $bankData['random_selection'] ?? true;

                //     // Get available questions from this group
                //     // $availableQuestions = Question::with('questionCategory')
                //     //     ->where('question_category_id', $exam->question_category_id)
                //     //     ->where('is_active', true)
                //     //     ->whereNull('deleted_at')
                //     //     ->get();

                //     // Select questions
                //     // if ($randomSelection) {
                //     //     $selectedQuestionsFromGroup = $availableQuestions->random($);
                //     // } else {
                //     //     $selectedQuestionsFromGroup = $availableQuestions->take($exam->total_questions);
                //     // }

                //     // // Create question_exam entries
                //     // foreach ($selectedQuestionsFromGroup as $question) {
                //     //     QuestionExam::create([
                //     //         'exam_id' => $exam->id,
                //     //         'question_id' => $question->id,
                //     //         'question_group_id' => $question->question_group_id,
                //     //         'question_number' => $questionNumber,
                //     //         'question_order' => $questionNumber, // Will be randomized per student later
                //     //         'is_active' => true,
                //     //     ]);

                //     //     $selectedQuestions[] = [
                //     //         'question_id' => $question->id,
                //     //         'question_group_id' => $question->question_group_id,
                //     //         'question_number' => $questionNumber,
                //     //         'question' => $question->question_text,
                //     //         'group_name' => $question->questionGroup->name,
                //     //     ];

                //     //     $questionNumber++;
                //     // }
                // }

                // Create question_exam entries
                foreach ($selectedQuestionsFromGroup as $question) {
                    QuestionExam::create([
                        'exam_id' => $exam->id,
                        'question_id' => $question->id,
                        'question_group_id' => $question->question_group_id,
                        'question_number' => $questionNumber,
                        'question_order' => $questionNumber, // Will be randomized per student later
                        'is_active' => true,
                    ]);

                    $selectedQuestions[] = [
                        'question_id' => $question->id,
                        'question_group_id' => $question->question_group_id,
                        'question_number' => $questionNumber,
                        'question' => $question->question_text,
                        'group_name' => $question->questionGroup->name,
                    ];

                    $questionNumber++;
                }

                // Update exam total questions
                $exam->update(['total_questions' => count($selectedQuestions)]);

                return $this->successResponse([
                    'exam_id' => $exam->id,
                    'total_questions' => count($selectedQuestions),
                    'selected_questions' => $selectedQuestions,
                    'question_banks' => $questionBanks,
                ], 'Questions successfully selected for exam');

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error selecting questions for exam: ' . $e->getMessage());
                return $this->errorResponse('Failed to select questions: ' . $e->getMessage());
            }
        });
    }

    /**
     * Generate random question order for student
     *
     * @param string $examId
     * @param string $studentId
     * @return array
     */
    public function generateRandomQuestionOrder(string $examId, string $userId): array
    {
        try {
            // Get all questions for this exam
            $examQuestions = QuestionExam::where('exam_id', $examId)
                ->where('is_active', true)
                ->with(['question', 'questionGroup'])
                ->get();

            if ($examQuestions->isEmpty()) {
                return $this->errorResponse('No questions found for this exam');
            }

            // Generate random order
            $shuffledQuestions = $examQuestions->shuffle();
            $questionOrderMap = [];

            foreach ($shuffledQuestions as $index => $examQuestion) {
                // Create a unique order for this student
                $questionOrderMap[$examQuestion->id] = $index + 1;
            }

            // Log the randomization for audit purposes
            Log::info('Question order generated for student', [
                'exam_id' => $examId,
                'user_id' => $userId,
                'question_count' => count($questionOrderMap),
                'timestamp' => Carbon::now()->toISOString(),
            ]);

            return $this->successResponse([
                'exam_id' => $examId,
                'user_id' => $userId,
                'question_order' => $questionOrderMap,
                'total_questions' => count($questionOrderMap),
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating random question order: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate question order: ' . $e->getMessage());
        }
    }

    /**
     * Get exam questions with proper ordering for student
     *
     * @param string $examId
     * @param string $studentId
     * @return array
     */
    public function getExamQuestionsForStudent(string $examId, string $userId): array
    {
        try {
            $exam = Exam::find($examId);

            if (!$exam) {
                return $this->errorResponse('Ujian tidak ditemukan', null, 404);
            }

            $student = Student::with('group')->where('user_id', $userId)->first();

            if (!$student) {
                return $this->errorResponse('Siswa tidak ditemukan', null, 404);
            }

            // Check if exam is available
            if (!$exam->isAvailable()) {
                return $this->errorResponse('Ujian tidak tersedia', null, 403);
            }

            $userExam = UserExam::where('exam_id', $examId)
                ->where('user_id', $userId)
                ->first();

            if (!$userExam) {
                return $this->errorResponse('Tidak terdaftar sebagai peserta ujian', null, 403);
            }

            // Generate random order for this student if not exists
            $orderResult = $this->generateRandomQuestionOrder($examId, $userId);

            if (!$orderResult['success']) {
                return $orderResult;
            }

            $questionOrderMap = $orderResult['data']['question_order'];

            // Get questions with proper ordering
            $questions = QuestionExam::where('exam_id', $examId)
                ->where('is_active', true)
                ->with(['question.options', 'questionGroup'])
                ->get()
                ->sortBy(function ($examQuestion) use ($questionOrderMap) {
                    return $questionOrderMap[$examQuestion->id] ?? 999;
                })
                ->values();

            // Format questions for response
            $formattedQuestions = $questions->map(function ($examQuestion, $index) use ($questionOrderMap) {
                return [
                    'id' => $examQuestion->id,
                    'question_number' => $examQuestion->question_number,
                    'question_order' => $questionOrderMap[$examQuestion->id] ?? ($index + 1),
                    'question_text' => $examQuestion->question->question_text,
                    'question_type' => $examQuestion->question->question_type,
                    'score' => $examQuestion->question->score,
                    'weight' => $examQuestion->weight,
                    'group_name' => $examQuestion->questionGroup->name,
                    'options' => $examQuestion->question->options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'option_text' => $option->option_text
                        ];
                    }),
                ];
            });

            return $this->successResponse([
                'exam' => $exam,
                'student' => $student,
                'questions' => $formattedQuestions,
                'total_questions' => count($formattedQuestions),
                'user_exam' => $userExam
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting exam questions for student: ' . $e->getMessage());
            return $this->errorResponse('Failed to get exam questions: ' . $e->getMessage());
        }
    }

    /**
     * Validate question bank selection
     *
     * @param array $questionBanks
     * @return array
     */
    public function validateQuestionBankSelection(array $questionBanks): array
    {
        try {
            $errors = [];
            $totalQuestions = 0;

            foreach ($questionBanks as $index => $bankData) {
                $questionGroupId = $bankData['question_group_id'] ?? null;
                $questionCount = $bankData['question_count'] ?? 0;

                if (!$questionGroupId) {
                    $errors[] = "Question group ID is required for bank selection " . ($index + 1);
                    continue;
                }

                if ($questionCount <= 0) {
                    $errors[] = "Question count must be greater than 0 for bank selection " . ($index + 1);
                    continue;
                }

                // Check if question group exists
                $questionGroup = QuestionGroup::find($questionGroupId);
                if (!$questionGroup) {
                    $errors[] = "Question group with ID {$questionGroupId} not found";
                    continue;
                }

                // Check if question group has enough questions
                $availableQuestions = Question::where('question_group_id', $questionGroupId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->count();

                if ($availableQuestions < $questionCount) {
                    $errors[] = "Question group '{$questionGroup->name}' only has {$availableQuestions} questions, but {$questionCount} requested";
                }

                $totalQuestions += $questionCount;
            }

            if (empty($errors)) {
                return $this->successResponse([
                    'total_questions' => $totalQuestions,
                    'question_banks' => $questionBanks,
                    'is_valid' => true,
                ], 'Question bank selection is valid');
            }

            return $this->errorResponse('Question bank selection validation failed',[
                'errors' => $errors,
                'is_valid' => false,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Validation error: ' . $e->getMessage());
        }
    }
}
