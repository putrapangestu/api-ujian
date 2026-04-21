<?php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionOption;
use App\Repositories\QuestionRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Question Service Class
 *
 * Service ini menangani semua operasi terkait soal dan opsi jawaban
 *
 * @author Development Team
 *
 * @version 1.0.0
 */
class QuestionService extends BaseService
{
    protected QuestionRepositoryInterface $questionRepository;

    /**
     * QuestionService constructor
     */
    public function __construct(QuestionRepositoryInterface $questionRepository)
    {
        $this->questionRepository = $questionRepository;
    }

    /**
     * Get all questions (paginated)
     */
    public function getAllQuestions(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = Question::query()
            ->with(['options', 'questionGroup','category']);

            if (isset($filters['exam_id']) && $filters['exam_id']) {
                $query->where('exam_id', $filters['exam_id']);
            }

            if (isset($filters['question_group_id']) && $filters['question_group_id']) {
                $query->where('question_group_id', $filters['question_group_id']);
            }

            $categoryId = $filters['question_category_id'] ?? ($filters['category_id'] ?? null);
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if (isset($filters['search']) && $filters['search']) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('question_text', 'like', "%{$search}%")
                        ->orWhere('correct_answer', 'like', "%{$search}%");
                });
            }

            if (isset($filters['question_type']) && $filters['question_type']) {
                $query->where('question_type', $filters['question_type']);
            }

            $questions = $query->orderByDesc('created_at')->paginate($perPage);

            return ResponseHelper::paginate($questions, 'Daftar soal berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar soal: '.$e->getMessage());
        }
    }

    /**
     * Get all question exams (paginated)
     */
    public function getAllQuestionExams(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = Question::query()
            ->with(['options', 'questionGroup','category', 'questionExams']);

            if (isset($filters['exam_id']) && $filters['exam_id']) {
                $query->whereRelation('questionExams','exam_id', $filters['exam_id'])->whereHas('questionExams');
            }

            if (isset($filters['question_group_id']) && $filters['question_group_id']) {
                $query->where('question_group_id', $filters['question_group_id']);
            }

            $categoryId = $filters['question_category_id'] ?? ($filters['category_id'] ?? null);
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if (isset($filters['search']) && $filters['search']) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('question_text', 'like', "%{$search}%")
                        ->orWhere('correct_answer', 'like', "%{$search}%");
                });
            }

            if (isset($filters['question_type']) && $filters['question_type']) {
                $query->where('question_type', $filters['question_type']);
            }

            $questions = $query->orderByDesc('created_at')->paginate($perPage);

            return ResponseHelper::paginate($questions, 'Daftar soal berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar soal: '.$e->getMessage());
        }
    }

    /**
     * Create new question with options
     *
     * @param  array  $data  Validated data from QuestionRequest
     */
    public function createQuestion(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                // Create question
                if (isset($data['question_group_id'])) {
                    $questionGroup = QuestionGroup::find($data['question_group_id']);
                    if (! $questionGroup) {
                        return $this->errorResponse('Grup soal tidak ditemukan', null, 404);
                    }

                    $data['question_category_id'] = $questionGroup->question_category_id;
                }

                $createData = $data;
                unset($createData['options']);
                $question = Question::create($createData);

                // Create options for multiple choice questions
                if (isset($data['options'])) {
                    foreach ($data['options'] as $optionData) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => $optionData['option_text'],
                            'is_correct' => $optionData['is_correct'],
                            'score' => $optionData['score'],
                            'option_label' => $optionData['label'] ?? null,
                        ]);
                    }
                }

                // Load relationships
                $question->loadMissing(['options', 'category', 'questionGroup']);

                return $this->successResponse($question, 'Soal berhasil dibuat', 201);

            } catch (\Exception $e) {
                DB::rollBack();

                return $this->errorResponse('Gagal membuat soal: '.$e->getMessage());
            }
        });
    }

    /**
     * Update question with options
     *
     * @param  array  $data  Validated data from QuestionRequest
     */
    public function updateQuestion(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            try {
                // Find question
                $question = Question::find($id);
                if (! $question) {
                    return $this->errorResponse('Soal tidak ditemukan', null, 404);
                }

                // Check if we need to version the question
                if ($question->hasBeenUsedInExam()) {
                    return $this->versionQuestion($question, $data);
                }

                // Update question in-place
                $updateData = $data;
                unset($updateData['options']);
                $question->update($updateData);

                // Update options for multiple choice questions
                if (($question->question_type === 'multiple_choice' || $question->question_type === 'single_choice') && isset($data['options'])) {
                    // Create new options
                    $existingOptions = $question->options;
                    $getCheckOptions = isset($data['options']) ? collect($data['options'])->pluck('id')->filter()->toArray() : [];
                    if(count($getCheckOptions)) {
                        $existingOptions->whereNotIn('id', $getCheckOptions)->each->delete();
                    } elseif (empty($getCheckOptions)) {
                        // If no IDs provided in request but we have options, delete all existing options
                        $existingOptions->each->delete();
                    }

                    foreach ($data['options'] as $optionData) {
                        // Delete existing options
                        $existingOption = null;
                        if (isset($optionData['id'])) {
                            $existingOption = $existingOptions->where('id', $optionData['id'])->first();
                        }

                        if ($existingOption) {
                            $existingOption->update([
                                'option_text' => $optionData['option_text'],
                                'is_correct' => $optionData['is_correct'],
                                'score' => $optionData['score'],
                                'option_label' => $optionData['label'] ?? null,
                            ]);
                        } else {
                            QuestionOption::create([
                                'question_id' => $question->id,
                                'option_text' => $optionData['option_text'],
                                'is_correct' => $optionData['is_correct'],
                                'score' => $optionData['score'],
                                'option_label' => $optionData['label'] ?? null,
                            ]);
                        }
                    }
                }

                // Load relationships
                $question->loadMissing(['options', 'category', 'questionGroup']);

                return $this->successResponse($question, 'Soal berhasil diupdate');

            } catch (\Exception $e) {
                DB::rollBack();

                return $this->errorResponse('Gagal update soal: '.$e->getMessage());
            }
        });
    }

    /**
     * Create a new version of a used question and soft delete the old one.
     *
     * @param Question $oldQuestion
     * @param array $data
     * @return array
     */
    private function versionQuestion(Question $oldQuestion, array $data): array
    {
        // 1. Prepare new question data (merge old unchanged data with new updates)
        $newQuestionData = [
            'exam_id' => $data['exam_id'] ?? $oldQuestion->exam_id,
            'question_group_id' => $data['question_group_id'] ?? $oldQuestion->question_group_id,
            'category_id' => $data['category_id'] ?? $oldQuestion->category_id,
            'question_text' => $data['question_text'] ?? $oldQuestion->question_text,
            'question_type' => $data['question_type'] ?? $oldQuestion->question_type,
            'score' => $data['score'] ?? $oldQuestion->score,
            'correct_answer' => $data['correct_answer'] ?? $oldQuestion->correct_answer,
        ];

        // 2. Create the new question
        $newQuestion = Question::create($newQuestionData);

        // 3. Create options for the new question
        if (($newQuestion->question_type === 'multiple_choice' || $newQuestion->question_type === 'single_choice') && isset($data['options'])) {
            foreach ($data['options'] as $optionData) {
                QuestionOption::create([
                    'question_id' => $newQuestion->id,
                    'option_text' => $optionData['option_text'],
                    'is_correct' => $optionData['is_correct'],
                    'score' => $optionData['score'],
                    'option_label' => $optionData['label'] ?? null,
                ]);
            }
        } elseif (($newQuestion->question_type === 'multiple_choice' || $newQuestion->question_type === 'single_choice')) {
            // Jika update tidak membawa data opsi baru, copy dari opsi lama
            foreach ($oldQuestion->options as $oldOption) {
                QuestionOption::create([
                    'question_id' => $newQuestion->id,
                    'option_text' => $oldOption->option_text,
                    'is_correct' => $oldOption->is_correct,
                    'score' => $oldOption->score,
                    'option_label' => $oldOption->option_label,
                ]);
            }
        }

        // 4. Soft delete the old question
        $oldQuestion->delete();

        // 5. Update total questions for the group if needed
        if ($newQuestion->question_group_id) {
            $group = QuestionGroup::find($newQuestion->question_group_id);
            if ($group) {
                $group->updateTotalQuestions();
            }
        }

        $newQuestion->loadMissing(['options', 'category', 'questionGroup']);

        return $this->successResponse($newQuestion, 'Soal berhasil diperbarui (versi baru dibuat karena soal sudah pernah digunakan)');
    }

    /**
     * Import questions from file
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     */
    public function importQuestions($file, string $examId, ?string $questionGroupId = null): array
    {
        return DB::transaction(function () use ($file, $examId, $questionGroupId) {
            try {
                // Validate exam
                $exam = Exam::find($examId);
                if (! $exam) {
                    return $this->errorResponse('Ujian tidak ditemukan', null, 404);
                }

                // Process file
                $filePath = $file->store('temp');
                $fileFullPath = storage_path('app/'.$filePath);

                // Read Excel file
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileFullPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Remove header row
                array_shift($rows);

                $questions = [];
                $errors = [];
                $rowNumber = 1;

                foreach ($rows as $row) {
                    $rowNumber++;

                    // Validasi row data
                    if (count($row) < 5) {
                        $errors[] = "Baris $rowNumber: Data tidak lengkap";

                        continue;
                    }

                    $questionText = trim($row[0]);
                    $questionType = trim($row[1]);
                    $categoryId = trim($row[2]);
                    $score = trim($row[3]);
                    $correctAnswerLabel = trim($row[4]);

                    if (empty($questionText) || empty($questionType)) {
                        $errors[] = "Baris $rowNumber: Teks soal dan tipe soal wajib diisi";

                        continue;
                    }

                    // Validasi tipe soal
                    if (! in_array($questionType, ['multiple_choice', 'essay', 'true_false', 'single_choice'])) {
                        $errors[] = "Baris $rowNumber: Tipe soal tidak valid";

                        continue;
                    }

                    // Handle grup soal
                    $finalQuestionGroupId = $questionGroupId;
                    if (isset($row[5]) && ! empty(trim($row[5]))) {
                        $groupName = trim($row[5]);
                        $code = strtolower(\Illuminate\Support\Str::slug($groupName, '_'));
                        $group = \App\Models\QuestionGroup::query()
                            ->where('code', $code)
                            ->orWhere('name', $groupName)
                            ->first();
                        
                        if ($group) {
                            $finalQuestionGroupId = $group->id;
                        }
                    }

                    // Parse options untuk multiple choice
                    $optionsData = [];
                    if ($questionType === 'multiple_choice' || $questionType === 'single_choice') {
                        $optionIndex = 6;
                        $optionLabel = 'A';

                        while ($optionIndex < count($row) && ! empty(trim($row[$optionIndex]))) {
                            $optionText = trim($row[$optionIndex]);
                            $optionScore = trim($row[$optionIndex + 1] ?? '0');
                            if ($optionScore === '') $optionScore = '0';
                            
                            $isCorrect = ($optionLabel === $correctAnswerLabel);

                            $optionsData[] = [
                                'text' => $optionText,
                                'is_correct' => $isCorrect,
                                'score' => (int) $optionScore,
                                'label' => $optionLabel,
                            ];

                            $optionIndex += 2;
                            $optionLabel = chr(ord($optionLabel) + 1);
                        }
                    }

                    $questionData = [
                        'exam_id' => $examId,
                        'question_group_id' => $finalQuestionGroupId,
                        'category_id' => $categoryId,
                        'question_text' => $questionText,
                        'question_type' => $questionType,
                        'score' => $score,
                        'correct_answer' => $correctAnswerLabel,
                        'options' => $optionsData,
                    ];

                    $result = $this->createQuestion($questionData);

                    if ($result['success']) {
                        $questions[] = $result['data'];
                    } else {
                        $errors[] = "Baris $rowNumber: ".$result['message'];
                    }
                }

                // Clean up file
                \Illuminate\Support\Facades\Storage::delete($filePath);

                return $this->successResponse([
                    'imported' => $questions,
                    'errors' => $errors,
                    'total_imported' => count($questions),
                    'total_errors' => count($errors),
                ], 'Import soal selesai');

            } catch (\Exception $e) {
                DB::rollBack();

                return $this->errorResponse('Gagal import soal: '.$e->getMessage());
            }
        });
    }

    /**
     * Delete question
     *
     * @param string $id
     * @return array
     */
    public function deleteQuestion(string $id): array
    {
        return DB::transaction(function () use ($id) {
            try {
                // Find question
                $question = Question::find($id);
                if (! $question) {
                    return $this->errorResponse('Soal tidak ditemukan', null, 404);
                }

                // Check if question is used in any exam
                if ($question->hasBeenUsedInExam()) {
                    return $this->errorResponse('Soal tidak dapat dihapus karena sudah pernah digunakan dalam ujian yang sedang atau sudah berjalan.');
                }

                $questionGroupId = $question->question_group_id;

                // Delete options first (if not cascading)
                $question->options()->delete();
                
                // Delete question
                $question->delete();

                // Update total questions for the group if needed
                if ($questionGroupId) {
                    $group = QuestionGroup::find($questionGroupId);
                    if ($group) {
                        $group->updateTotalQuestions();
                    }
                }

                return $this->successResponse(null, 'Soal berhasil dihapus');
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menghapus soal: ' . $e->getMessage());
            }
        });
    }
}
