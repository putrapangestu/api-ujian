<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuestionGroup;
use App\Models\QuestionOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * QuestionBank Service Class
 *
 * Service ini menangani import bank soal yang komprehensif
 * Mendukung berbagai format dan validasi yang kompleks
 *
 * @author Development Team
 *
 * @version 1.0.0
 */
class QuestionBankService extends BaseService
{
    /**
     * Import question bank from Excel file
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     */
    public function importQuestionBank($file, ?string $examId = null, ?string $questionGroupId = null, ?string $categoryId = null): array
    {
        return DB::transaction(function () use ($file, $examId, $questionGroupId, $categoryId) {
            try {
                // Process file
                $filePath = $file->store('temp');
                $fileFullPath = storage_path('app/'.$filePath);

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
                        $errors[] = "Baris $rowNumber: ".$e->getMessage();
                    }
                }

                // Clean up file
                Storage::delete($filePath);

                // Update question group total
                if ($questionGroupId) {
                    $questionGroup = QuestionGroup::find($questionGroupId);
                    $questionGroup->updateTotalQuestions();
                }

                return $this->successResponse([
                    'imported' => $questions,
                    'errors' => $errors,
                    'total_imported' => count($questions),
                    'total_errors' => count($errors),
                ], 'Import bank soal selesai');

            } catch (\Exception $e) {
                DB::rollBack();

                return $this->errorResponse('Gagal import bank soal: '.$e->getMessage());
            }
        });
    }

    public function importMoodleXml($file, ?string $examId = null): array
    {
        return DB::transaction(function () use ($file, $examId) {
            try {
                if ($examId && ! Exam::find($examId)) {
                    return $this->errorResponse('Ujian tidak ditemukan');
                }

                $filePath = $file->store('temp');
                $fileFullPath = storage_path('app/'.$filePath);

                $xmlContent = file_get_contents($fileFullPath);
                if ($xmlContent === false || trim($xmlContent) === '') {
                    Storage::delete($filePath);

                    return $this->errorResponse('File XML kosong atau tidak bisa dibaca');
                }

                $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
                if (! $xml) {
                    Storage::delete($filePath);

                    return $this->errorResponse('Format XML tidak valid');
                }

                $questions = [];
                $errors = [];
                $itemNumber = 0;

                foreach ($xml->question as $questionNode) {
                    $itemNumber++;
                    $type = (string) ($questionNode['type'] ?? '');

                    if ($type === '' || $type === 'category') {
                        continue;
                    }

                    try {
                        $result = $this->processMoodleQuestionNode($questionNode, $itemNumber, $examId);

                        if ($result['success']) {
                            $questions[] = $result['data'];
                        } else {
                            $errors[] = $result['message'];
                        }
                    } catch (\Exception $e) {
                        $errors[] = 'Item '.$itemNumber.': '.$e->getMessage();
                    }
                }

                Storage::delete($filePath);

                $groupIds = collect($questions)
                    ->map(function ($question) {
                        return $question instanceof Question ? $question->question_group_id : null;
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                foreach ($groupIds as $groupId) {
                    $group = QuestionGroup::find($groupId);
                    if ($group) {
                        $group->updateTotalQuestions();
                    }
                }

                return $this->successResponse([
                    'imported' => $questions,
                    'errors' => $errors,
                    'total_imported' => count($questions),
                    'total_errors' => count($errors),
                ], 'Import Moodle XML selesai');
            } catch (\Exception $e) {
                DB::rollBack();

                return $this->errorResponse('Gagal import Moodle XML: '.$e->getMessage());
            }
        });
    }

    private function processMoodleQuestionNode(\SimpleXMLElement $questionNode, int $itemNumber, ?string $examId): array
    {
        $type = (string) ($questionNode['type'] ?? '');
        $questionType = match ($type) {
            'multichoice' => 'multiple_choice',
            'truefalse' => 'true_false',
            'essay' => 'essay',
            'singlechoice' => 'single_choice',
            default => null,
        };

        if (! $questionType) {
            return $this->errorResponse('Item '.$itemNumber.': tipe soal tidak didukung ('.$type.')');
        }

        $defaultMark = $this->getMoodleNumber($questionNode->defaultmark) ?? $this->getMoodleNumber($questionNode->defaultgrade) ?? 1;
        $score = (int) max(1, round($defaultMark));

        $questionText = $this->getMoodleHtmlText($questionNode->questiontext);
        if (trim(strip_tags($questionText)) === '') {
            return $this->errorResponse('Item '.$itemNumber.': teks soal kosong');
        }

        $filesByName = $this->extractMoodleFiles($questionNode);

        $tags = $this->getMoodleTags($questionNode);
        if (empty($tags)) {
            return $this->errorResponse('Item '.$itemNumber.': tags wajib diisi untuk menentukan grup soal');
        }

        $group = $this->findQuestionGroupFromTags($tags);
        if (!$group) {
            return $this->errorResponse('Item '.$itemNumber.': tag tidak ditemukan di QuestionGroup ('.implode(', ', $tags).')');
        }

        if (!$group->question_category_id) {
            return $this->errorResponse('Item '.$itemNumber.': category dari tag tidak ditemukan di QuestionGroup ('.implode(', ', $tags).')');
        }

        $finalQuestionGroupId = $group->id;
        $finalCategoryId = $group->question_category_id ?: null;

        $question = Question::create([
            'exam_id' => $examId,
            'question_group_id' => $finalQuestionGroupId,
            'category_id' => $finalCategoryId,
            'question_text' => $questionText,
            'question_type' => $questionType,
            'score' => $score,
            'correct_answer' => null,
        ]);

        $questionTextWithFiles = $this->replacePluginFileReferences($question->id, $questionText, $filesByName);
        if ($questionTextWithFiles !== $questionText) {
            $question->update(['question_text' => $questionTextWithFiles]);
        }

        if ($questionType === 'essay') {
            $question->load(['options', 'category', 'questionGroup']);

            return $this->successResponse($question, 'Soal berhasil dibuat');
        }

        if ($questionType === 'true_false') {
            $tfResult = $this->createTrueFalseOptions($question, $questionNode, $filesByName);
            if (! $tfResult['success']) {
                return $tfResult;
            }

            $question->load(['options', 'category', 'questionGroup']);

            return $this->successResponse($question, 'Soal berhasil dibuat');
        }

        if ($questionType === 'single_choice') {
            $scResult = $this->createTrueFalseOptions($question, $questionNode, $filesByName);
            if (! $scResult['success']) {
                return $scResult;
            }

            $question->load(['options', 'category', 'questionGroup']);

            return $this->successResponse($question, 'Soal berhasil dibuat');
        }

        $mcResult = $this->createMultipleChoiceOptions($question, $questionNode, $filesByName, $score);
        if (! $mcResult['success']) {
            return $mcResult;
        }

        $question->load(['options', 'category', 'questionGroup']);

        return $this->successResponse($question, 'Soal berhasil dibuat');
    }

    private function getMoodleHtmlText(?\SimpleXMLElement $node): string
    {
        if (! $node) {
            return '';
        }

        $textNode = $node->text ?? null;
        if (! $textNode) {
            return '';
        }

        return (string) $textNode;
    }

    private function getMoodleNumber(?\SimpleXMLElement $node): ?float
    {
        if (! $node) {
            return null;
        }

        $value = trim((string) $node);
        if ($value === '') {
            return null;
        }

        return (float) $value;
    }

    private function extractMoodleFiles(\SimpleXMLElement $questionNode): array
    {
        $files = [];
        $fileNodes = $questionNode->xpath('.//file') ?: [];

        foreach ($fileNodes as $fileNode) {
            $name = (string) ($fileNode['name'] ?? '');
            $encoding = strtolower((string) ($fileNode['encoding'] ?? ''));
            $content = (string) $fileNode;

            if ($name === '' || $content === '') {
                continue;
            }

            if ($encoding === 'base64') {
                $decoded = base64_decode(preg_replace('/\s+/', '', $content), true);
                if ($decoded === false) {
                    continue;
                }

                $files[$name] = $decoded;

                continue;
            }

            $files[$name] = $content;
        }

        return $files;
    }

    private function replacePluginFileReferences(string $questionId, string $html, array $filesByName): string
    {
        if (trim($html) === '' || empty($filesByName)) {
            return $html;
        }

        $storageDir = 'questions/moodle/'.$questionId;

        foreach ($filesByName as $name => $content) {
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: Str::random(12);
            $relativePath = $storageDir.'/'.$safeName;

            Storage::disk('public')->put($relativePath, $content);
            $url = asset('storage/'.$relativePath);

            $html = str_replace('@@PLUGINFILE@@/'.$name, $url, $html);
            $html = str_replace('@@PLUGINFILE@@/'.$safeName, $url, $html);
        }

        return $html;
    }

    private function getFirstMoodleTag(\SimpleXMLElement $questionNode): ?string
    {
        $tags = $this->getMoodleTags($questionNode);
        if (empty($tags)) {
            return null;
        }

        return $tags[0];
    }

    private function getMoodleTags(\SimpleXMLElement $questionNode): array
    {
        if (! isset($questionNode->tags)) {
            return [];
        }

        $values = [];
        foreach ($questionNode->tags->tag as $tag) {
            $value = isset($tag->text) ? (string) $tag->text : (string) $tag;
            $value = trim($value);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function findQuestionGroupFromTags(array $tags): ?QuestionGroup
    {
        foreach ($tags as $tag) {
            $code = strtolower(Str::slug($tag, '_'));

            $query = QuestionGroup::query()
                ->where('code', $code)
                ->orWhere('name', $tag);

            $group = $query->first();
            if ($group) {
                return $group;
            }
        }

        return null;
    }

    private function createTrueFalseOptions(Question $question, \SimpleXMLElement $questionNode, array $filesByName): array
    {
        $answers = $questionNode->answer ?? null;
        if (! $answers || count($answers) < 2) {
            return $this->errorResponse('Jawaban yang di set harus lebih dari 1');
        }

        $hasOptionScore = Schema::hasColumn('question_options', 'score');
        $createdOptions = [];

        foreach ($questionNode->answer as $answerNode) {
            $fraction = (float) ($answerNode['fraction'] ?? 0);
            $answerText = $this->getMoodleAnswerText($answerNode);
            $answerText = $this->replacePluginFileReferences($question->id, $answerText, $filesByName);
            $normalized = strtolower(trim(strip_tags($answerText)));

            if ($normalized !== 'true' && $normalized !== 'false') {
                continue;
            }

            $data = [
                'question_id' => $question->id,
                'option_text' => $answerText,
                'is_correct' => $fraction > 0,
            ];

            if ($hasOptionScore) {
                $data['score'] = (int) round($fraction);
            }

            $createdOptions[] = QuestionOption::create($data);
        }

        if (count($createdOptions) < 2) {
            return $this->errorResponse('Soal true/false wajib memiliki jawaban True dan False');
        }

        $correct = collect($createdOptions)->firstWhere('is_correct', true);
        if (! $correct) {
            return $this->errorResponse('Soal true/false harus memiliki jawaban yang benar');
        }

        $question->update([
            'correct_answer' => strtolower(trim(strip_tags($correct->option_text))),
        ]);

        return $this->successResponse(true, 'Ok');
    }

    private function createMultipleChoiceOptions(Question $question, \SimpleXMLElement $questionNode, array $filesByName, int $questionScore): array
    {
        $answers = $questionNode->answer ?? null;
        if (! $answers || count($answers) < 2) {
            return $this->errorResponse('Soal pilihan ganda minimal memiliki 2 jawaban');
        }

        $hasOptionScore = Schema::hasColumn('question_options', 'score');
        $options = [];

        foreach ($questionNode->answer as $answerNode) {
            $fraction = (float) ($answerNode['fraction'] ?? 0);
            $optionText = $this->getMoodleAnswerText($answerNode);
            $optionText = $this->replacePluginFileReferences($question->id, $optionText, $filesByName);

            if (trim(strip_tags($optionText)) === '') {
                continue;
            }

            $data = [
                'question_id' => $question->id,
                'option_text' => $optionText,
                'is_correct' => $fraction > 0,
            ];

            if ($hasOptionScore) {
                $data['score'] = (int) round($fraction);
            }

            $options[] = QuestionOption::create($data);
        }

        if (count($options) < 2) {
            return $this->errorResponse('Soal pilihan ganda minimal memiliki 2 opsi');
        }

        $correct = collect($options)->firstWhere('is_correct', true);
        if (! $correct) {
            return $this->errorResponse('Soal pilihan ganda harus memiliki jawaban benar');
        }

        $question->update([
            'correct_answer' => $correct->option_text,
            'score' => $questionScore,
        ]);

        return $this->successResponse(true, 'Ok');
    }

    private function getMoodleAnswerText(\SimpleXMLElement $answerNode): string
    {
        if (isset($answerNode->text)) {
            return (string) $answerNode->text;
        }

        return trim((string) $answerNode);
    }

    /**
     * Process single question row
     *
     * @param  int|null  $examId
     * @param  int|null  $questionGroupId
     * @param  int|null  $categoryId
     */
    private function processQuestionRow(array $row, int $rowNumber, ?string $examId, ?string $questionGroupId, ?string $categoryId): array
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

        if (! in_array($questionType, ['multiple_choice', 'essay', 'true_false', 'single_choice'])) {
            return $this->errorResponse("Baris $rowNumber: Tipe soal tidak valid");
        }

        // Handle kategori
        $finalCategoryId = $categoryId;
        if ($questionGroupId) {
            $group = QuestionGroup::find($questionGroupId);
            if ($group && $group->question_category_id) {
                $finalCategoryId = $group->question_category_id;
            }
        }
        if (! empty($categoryName) && ! $categoryId) {
            $category = QuestionCategory::firstOrCreate(
                ['name' => $categoryName],
                ['code' => strtoupper(str_replace(' ', '_', $categoryName)), 'status' => 'active']
            );
            $finalCategoryId = $category->id;
        }

        // Handle grup soal
        $finalQuestionGroupId = $questionGroupId;
        if (isset($row[5]) && ! empty(trim($row[5]))) {
            $groupName = trim($row[5]);
            $code = strtolower(Str::slug($groupName, '_'));
            $group = QuestionGroup::query()
                ->where('code', $code)
                ->orWhere('name', $groupName)
                ->first();

            if (! $group) {
                return $this->errorResponse("Baris $rowNumber: Grup soal tidak ditemukan ($groupName)");
            }

            $finalQuestionGroupId = $group->id;
            if ($group->question_category_id) {
                $finalCategoryId = $group->question_category_id;
            }
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

            while ($optionIndex < count($row) && ! empty(trim($row[$optionIndex]))) {
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

            if (! $question->options->where('is_correct', true)->first()) {
                return $this->errorResponse("Baris $rowNumber: Tidak ada opsi yang benar");
            }
        }

        // Load relationships
        $question->loadMissing(['options', 'category', 'questionGroup']);

        return $this->successResponse($question, 'Soal berhasil dibuat');
    }

    /**
     * Export question bank to Excel
     *
     * @param  int|null  $examId
     * @param  int|null  $questionGroupId
     * @param  int|null  $categoryId
     */
    public function exportQuestionBank(?string $examId = null, ?string $questionGroupId = null, ?string $categoryId = null): array
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
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
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
                $sheet->setCellValue('A'.$row, $question->question_text);
                $sheet->setCellValue('B'.$row, $question->question_type);
                $sheet->setCellValue('C'.$row, $question->category->name ?? '');
                $sheet->setCellValue('D'.$row, $question->score);
                $sheet->setCellValue('E'.$row, $question->correct_answer ?? '');
                $sheet->setCellValue('F'.$row, $question->questionGroup->name ?? '');

                // Set options for multiple choice
                if ($question->question_type === 'multiple_choice') {
                    $options = $question->options->sortBy('option_label');
                    $optionColumn = 'G';
                    foreach ($options as $option) {
                        $sheet->setCellValue($optionColumn.$row, $option->option_text);
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
            $filename = 'question_bank_'.date('Y-m-d_H-i-s').'.xlsx';
            $filePath = 'exports/'.$filename;
            $fullPath = storage_path('app/'.$filePath);

            // Ensure directory exists
            if (! file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($fullPath);

            return $this->successResponse([
                'file_path' => $filePath,
                'file_name' => $filename,
                'download_url' => asset('storage/'.$filePath),
                'total_questions' => $questions->count(),
            ], 'Export bank soal berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal export bank soal: '.$e->getMessage());
        }
    }
}
