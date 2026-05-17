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
     * Logika pemilihan soal:
     * 1. Soal dikelompokkan berdasarkan QuestionGroup
     * 2. Setiap grup memiliki display_question_count → jumlah soal yang diambil dari grup tsb
     * 3. QuestionCategory memiliki range start_range s/d end_range → nomor urut soal dalam ujian
     * 4. Soal diambil secara acak dari bank soal grup, sejumlah display_question_count
     *    (atau semua jika display_question_count = 0)
     * 5. Nomor soal diberikan berurutan sesuai range kategori
     *
     * @param Exam $exam
     * @param array $questionBanks Array of question_ids
     * @return array
     */
    public function selectQuestionsForExam(Exam $exam, array $questionBanks): array
    {
        return DB::transaction(function () use ($exam, $questionBanks) {
            try {
                $selectedQuestions = [];
                $questionNumber    = 1;

                // Ambil semua soal yang dipilih beserta relasi group & category
                $questions = Question::with(['category', 'questionGroup.questionCategory'])
                    ->whereIn('id', $questionBanks)
                    ->whereNull('deleted_at')
                    ->get();

                // Kelompokkan soal berdasarkan question_group_id
                $groupedByGroup = $questions->groupBy('question_group_id');

                foreach ($groupedByGroup as $groupId => $questionsInGroup) {
                    /** @var \App\Models\QuestionGroup|null $group */
                    $group    = $questionsInGroup->first()?->questionGroup;
                    $category = $group?->questionCategory;

                    // Tentukan starting number dari range kategori (start_range)
                    if ($category && isset($category->start_range) && $category->start_range > 0) {
                        $questionNumber = $category->start_range;
                    }

                    // Tentukan berapa soal yang diambil dari grup ini
                    $displayCount = $group?->display_question_count ?? 0;

                    if ($displayCount > 0 && $questionsInGroup->count() > $displayCount) {
                        // Ambil sejumlah display_question_count secara acak
                        $selectedFromGroup = $questionsInGroup->shuffle()->take($displayCount);
                    } else {
                        // Tampilkan semua soal di grup ini (acak urutannya)
                        $selectedFromGroup = $questionsInGroup->shuffle();
                    }

                    // Buat question_exam entries
                    foreach ($selectedFromGroup as $question) {
                        QuestionExam::create([
                            'exam_id'          => $exam->id,
                            'question_id'      => $question->id,
                            'question_group_id' => $question->question_group_id,
                            'question_number'  => $questionNumber,
                            'question_order'   => $questionNumber,
                            'is_active'        => true,
                        ]);

                        $selectedQuestions[] = [
                            'question_id'       => $question->id,
                            'question_group_id' => $question->question_group_id,
                            'question_number'   => $questionNumber,
                            'question'          => $question->question_text,
                            'group_name'        => $group?->name,
                            'category_name'     => $category?->name,
                        ];

                        $questionNumber++;
                    }
                }

                // Update exam total questions
                // $exam->update(['total_questions' => count($selectedQuestions)]);

                return $this->successResponse([
                    'exam_id'            => $exam->id,
                    'total_questions'    => count($selectedQuestions),
                    'selected_questions' => $selectedQuestions,
                    'question_banks'     => $questionBanks,
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

    /**
     * Generate exam questions based on categories, ranges, and question groups.
     * Dipanggil ketika pengacakan soal siswa saat start ujian.
     *
     * @param Exam $exam
     * @return \Illuminate\Support\Collection
     */
    public function generateExamQuestions(Exam $exam)
    {
        // Catatan: Pastikan model Exam memiliki relasi categories() atau questionCategories()
        // yang mengarah ke QuestionCategory dengan membawa pivot 'question_count'.
        $examCategories = $exam->categories ?? $exam->questionCategories ?? collect();
        $selectedQuestions = collect();

        if ($examCategories->isEmpty()) {
            return $selectedQuestions;
        }

        foreach ($examCategories as $category) {
            $categoryStartRange = $category->start_range;
            $categoryEndRange = $category->end_range;
            $neededQuestionCount = $category->pivot->question_count ?? 0;

            // Ambil semua grup soal untuk kategori ini yang berstatus aktif
            $groups = QuestionGroup::where('question_category_id', $category->id)
                        ->where('status', QuestionGroup::STATUS_ACTIVE)
                        ->get();

            $categoryQuestions = collect();
            $validGroups = collect();
            $invalidGroups = collect();

            // Pisahkan grup yang valid sesuai rentang (range) dengan yang tidak
            foreach ($groups as $group) {
                $isValidRange = !is_null($group->number_start) && !is_null($group->number_end) &&
                                !is_null($categoryStartRange) && !is_null($categoryEndRange) &&
                                ($group->number_start >= $categoryStartRange) &&
                                ($group->number_end <= $categoryEndRange);

                if ($isValidRange) {
                    $validGroups->push($group);
                } else {
                    $invalidGroups->push($group);
                }
            }

            // Urutkan grup yang valid berdasarkan number_start
            $validGroups = $validGroups->sortBy('number_start');

            // Proses grup yang valid: ambil soal secara acak sesuai dengan display_question_count
            foreach ($validGroups as $group) {
                $questions = $group->questions()
                    ->inRandomOrder()
                    ->take($group->display_question_count)
                    ->get();
                $categoryQuestions = $categoryQuestions->merge($questions);
            }

            // Proses grup yang tidak valid (tidak memiliki number_start/end atau di luar rentang)
            foreach ($invalidGroups as $group) {
                // Cari question group lain di kategori yang sama
                $otherGroups = $groups->where('id', '!=', $group->id);

                // Ganti dengan question group lain secara acak jika ada, jika tidak tetap pakai yang awal
                $replacementGroup = $otherGroups->isNotEmpty() ? $otherGroups->random() : $group;

                $questions = $replacementGroup->questions()
                    ->inRandomOrder()
                    ->take($group->display_question_count) // Tetap dibatasi dengan display_question_count dari group asli
                    ->get();

                $categoryQuestions = $categoryQuestions->merge($questions);
            }

            // Batasi total soal yang diambil agar pas dengan alokasi question_count yang dibutuhkan pada kategori
            $finalCategoryQuestions = $categoryQuestions->take($neededQuestionCount);
            $selectedQuestions = $selectedQuestions->merge($finalCategoryQuestions);
        }

        return $selectedQuestions;
    }
}
