<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionGroup;
use App\Models\QuestionCategory;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * QuestionBank Service Class
 * 
 * Service ini menangani import bank soal yang komprehensif
 * Mendukung berbagai format dan validasi yang kompleks
 * 
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class QuestionBankService extends BaseService
{
    /**
     * Import question bank from Excel file
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param int|null $examId
     * @param int|null $questionGroupId
     * @param int|null $categoryId
     * @return array
     */
    public function importQuestionBank($file, ?int $examId = null, ?int $questionGroupId = null, ?int $categoryId = null): array
    {
        return DB::transaction(function () use ($file, $examId, $questionGroupId, $categoryId) {
            try {
                // Validate exam if provided
                if ($examId && !Exam::find($examId)) {
                    return $this->errorResponse('Ujian tidak ditemukan', null, 404);
                }

                // Validate question group if provided
                if ($questionGroupId && !QuestionGroup::find($questionGroupId)) {
                    return $this->errorResponse('Grup soal tidak ditemukan', null, 404);
                }

                // Validate category if provided
                if ($categoryId && !QuestionCategory::find($categoryId)) {
                    return $this->errorResponse('Kategori soal tidak ditemukan', null, 404);
                }

                // Process file
                $filePath = $file->store('temp');
                $fileFullPath = storage_path('app/' . $filePath);

                // Read Excel file
                $spreadsheet = IOFactory::load($fileFullPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Remove header row
                array_shift($rows);

                $questions = [];
                $errors = [];
                $rowNumber = 1;

                foreach ($rows as $row) {
                    $rowNumber++;

                    try {
                        $result = $this->processQuestionRow($row, $rowNumber, $examId, $questionGroupId, $categoryId);
                        
                        if ($result['success']) {
                            $questions[] = $result['data'];
                        } else {
                            $errors[] = $result['message'];
                        }

                    } catch (\Exception $e) {
                        $errors[] = "Baris $rowNumber: " . $e->getMessage();
                    }
                }

                // Clean up file
                \Illuminate\Support\Facades\Storage::delete($filePath);

                // Update question group total
                if ($questionGroupId) {
                    $questionGroup = QuestionGroup::find($questionGroupId);
                    $questionGroup->updateTotalQuestions();
                }

                return $this->successResponse([
                    'imported' => $questions,
                    'errors' => $errors,
                    'total_imported' => count($questions),
                    'total_errors' => count($errors)
                ], 'Import bank soal selesai');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal import bank soal: ' . $e->getMessage());
            }
        });
    }

    /**
     * Process single question row
     * 
     * @param array $row
     * @param int $rowNumber
     * @param int|null $examId
     * @param int|null $questionGroupId
     * @param int|null $categoryId
     * @return array
     */
    private function processQuestionRow(array $row, int $rowNumber, ?int $examId, ?int $questionGroupId, ?int $categoryId): array
    {
        // Validasi minimal data
        if (count($row) < 5) {
            return $this->errorResponse("Baris $rowNumber: Data tidak lengkap (minimal 5 kolom)");
        }

        $questionText = trim($row[0]);
        $questionType = trim($row[1]);
        $categoryName = trim($row[2] ?? '');
        $score = trim($row[3] ?? '1');
        $correctAnswer = trim($row[4] ?? '');

        // Validasi data wajib
        if (empty($questionText)) {
            return $this->errorResponse("Baris $rowNumber: Teks soal wajib diisi");
        }

        if (empty($questionType)) {
            return $this->errorResponse("Baris $rowNumber: Tipe soal wajib diisi");
        }

        if (!in_array($questionType, ['multiple_choice', 'essay', 'true_false'])) {
            return $this->errorResponse("Baris $rowNumber: Tipe soal tidak valid");
        }

        // Handle kategori
        $finalCategoryId = $categoryId;
        if (!empty($categoryName) && !$categoryId) {
            $category = QuestionCategory::firstOrCreate(
                ['name' => $categoryName],
                ['code' => strtoupper(str_replace(' ', '_', $categoryName)), 'status' => 'active']
            );
            $finalCategoryId = $category->id;
        }

        // Handle grup soal
        $finalQuestionGroupId = $questionGroupId;
        if (isset($row[5]) && !empty(trim($row[5])) && !$questionGroupId) {
            $groupName = trim($row[5]);
            $group = QuestionGroup::firstOrCreate(
                ['name' => $groupName],
                ['code' => strtoupper(str_replace(' ', '_', $groupName)), 'status' => 'active']
            );
            $finalQuestionGroupId = $group->id;
        }

        // Buat soal
        $question = Question::create([
            'exam_id' => $examId,
            'question_group_id' => $finalQuestionGroupId,
            'category_id' => $finalCategoryId,
            'question_text' => $questionText,
            'question_type' => $questionType,
            'score' => (int) $score,
            'correct_answer' => $correctAnswer,
        ]);

        // Handle opsi untuk multiple choice
        if ($questionType === 'multiple_choice') {
            if (count($row) < 7) {
                return $this->errorResponse("Baris $rowNumber: Opsi untuk multiple choice tidak ditemukan");
            }

            $options = [];
            $optionIndex = 6;
            $optionLabel = 'A';

            while ($optionIndex < count($row) && !empty(trim($row[$optionIndex]))) {
                $optionText = trim($row[$optionIndex]);
                $isCorrect = $optionText === $correctAnswer;
                
                $options[] = QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => $isCorrect,
                    'score' => $isCorrect ? (int) $score : 0,
                    'option_label' => $optionLabel,
                ]);

                $optionIndex++;
                $optionLabel = chr(ord($optionLabel) + 1);
            }

            if (count($options) < 2) {
                return $this->errorResponse("Baris $rowNumber: Minimal 2 opsi untuk multiple choice");
            }

            if (!$options->where('is_correct', true)->first()) {
                return $this->errorResponse("Baris $rowNumber: Tidak ada opsi yang benar");
            }
        }

        // Load relationships
        $question->load(['options', 'category', 'questionGroup']);

        return $this->successResponse($question, "Soal berhasil dibuat");
    }

    /**
     * Export question bank to Excel
     * 
     * @param int|null $examId
     * @param int|null $questionGroupId
     * @param int|null $categoryId
     * @return array
     */
    public function exportQuestionBank(?int $examId = null, ?int $questionGroupId = null, ?int $categoryId = null): array
    {
        try {
            $query = Question::query();

            if ($examId) {
                $query->where('exam_id', $examId);
            }

            if ($questionGroupId) {
                $query->where('question_group_id', $questionGroupId);
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $questions = $query->with(['options', 'category', 'questionGroup', 'exam'])->get();

            if ($questions->isEmpty()) {
                return $this->errorResponse('Tidak ada soal untuk diekspor');
            }

            // Create Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'Teks Soal');
            $sheet->setCellValue('B1', 'Tipe Soal');
            $sheet->setCellValue('C1', 'Kategori');
            $sheet->setCellValue('D1', 'Nilai');
            $sheet->setCellValue('E1', 'Jawaban Benar');
            $sheet->setCellValue('F1', 'Grup Soal');
            $sheet->setCellValue('G1', 'Opsi A');
            $sheet->setCellValue('H1', 'Opsi B');
            $sheet->setCellValue('I1', 'Opsi C');
            $sheet->setCellValue('J1', 'Opsi D');
            $sheet->setCellValue('K1', 'Opsi E');

            // Set data
            $row = 2;
            foreach ($questions as $question) {
                $sheet->setCellValue('A' . $row, $question->question_text);
                $sheet->setCellValue('B' . $row, $question->question_type);
                $sheet->setCellValue('C' . $row, $question->category->name ?? '');
                $sheet->setCellValue('D' . $row, $question->score);
                $sheet->setCellValue('E' . $row, $question->correct_answer ?? '');
                $sheet->setCellValue('F' . $row, $question->questionGroup->name ?? '');

                // Set options for multiple choice
                if ($question->question_type === 'multiple_choice') {
                    $options = $question->options->sortBy('option_label');
                    $optionColumn = 'G';
                    foreach ($options as $option) {
                        $sheet->setCellValue($optionColumn . $row, $option->option_text);
                        $optionColumn = chr(ord($optionColumn) + 1);
                    }
                }

                $row++;
            }

            // Auto size columns
            foreach (range('A', 'K') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create file
            $filename = 'question_bank_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filePath = 'exports/' . $filename;
            $fullPath = storage_path('app/' . $filePath);

            // Ensure directory exists
            if (!file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($fullPath);

            return $this->successResponse([
                'file_path' => $filePath,
                'file_name' => $filename,
                'download_url' => asset('storage/' . $filePath),
                'total_questions' => $questions->count(),
            ], 'Export bank soal berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal export bank soal: ' . $e->getMessage());
        }
    }
}