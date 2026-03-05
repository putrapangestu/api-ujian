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
            ->with(['questionOptions', 'questionGroup','category']);

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
            ->with(['questionOptions', 'questionGroup','category', 'questionExams']);

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
                            'option_text' => $optionData['text'],
                            'is_correct' => $optionData['is_correct'],
                            'score' => $optionData['score'],
                            'option_label' => $optionData['label'] ?? null,
                        ]);
                    }
                }

                // Load relationships
                $question->loadMissing(['questionOptions', 'category', 'questionGroup']);

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

                // Update question
                $updateData = $data;
                unset($updateData['options']);
                $question->update($updateData);

                // Update options for multiple choice questions
                if ($question->question_type === 'multiple_choice' && isset($data['options'])) {
                    // Delete existing options
                    $question->questionOptions()->delete();

                    // Create new options
                    foreach ($data['options'] as $optionData) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => $optionData['text'],
                            'is_correct' => $optionData['is_correct'],
                            'score' => $optionData['score'],
                            'option_label' => $optionData['label'] ?? null,
                        ]);
                    }
                }

                // Load relationships
                $question->loadMissing(['questionOptions', 'category', 'questionGroup']);

                return $this->successResponse($question, 'Soal berhasil diupdate');

            } catch (\Exception $e) {
                DB::rollBack();

                return $this->errorResponse('Gagal update soal: '.$e->getMessage());
            }
        });
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
                    if (count($row) < 6) {
                        $errors[] = "Baris $rowNumber: Data tidak lengkap";

                        continue;
                    }

                    $questionText = trim($row[0]);
                    $questionType = trim($row[1]);
                    $categoryId = trim($row[2]);
                    $score = trim($row[3]);
                    $correctAnswer = trim($row[4]);
                    $options = trim($row[5]);

                    if (empty($questionText) || empty($questionType)) {
                        $errors[] = "Baris $rowNumber: Teks soal dan tipe soal wajib diisi";

                        continue;
                    }

                    // Validasi tipe soal
                    if (! in_array($questionType, ['multiple_choice', 'essay', 'true_false', 'single_choice'])) {
                        $errors[] = "Baris $rowNumber: Tipe soal tidak valid";

                        continue;
                    }

                    // Parse options untuk multiple choice
                    $optionsData = [];
                    if ($questionType === 'multiple_choice' && ! empty($options)) {
                        $optionTexts = explode('|', $options);
                        foreach ($optionTexts as $index => $optionText) {
                            $optionsData[] = [
                                'text' => trim($optionText),
                                'is_correct' => trim($optionText) === $correctAnswer,
                                'score' => trim($optionText) === $correctAnswer ? $score : 0,
                                'label' => chr(65 + $index), // A, B, C, D
                            ];
                        }
                    }

                    $questionData = [
                        'exam_id' => $examId,
                        'question_group_id' => $questionGroupId,
                        'category_id' => $categoryId,
                        'question_text' => $questionText,
                        'question_type' => $questionType,
                        'score' => $score,
                        'correct_answer' => $correctAnswer,
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
}
