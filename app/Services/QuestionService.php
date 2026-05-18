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
                        ->orWhere('correct_answer', 'like', "%{$search}%")
                        ->orWhereHas('options', function ($opt) use ($search) {
                            $opt->where('option_text', 'like', "%{$search}%");
                        });
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
            ->with(['options', 'questionGroup.questionCategory', 'questionExams']);

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
                        ->orWhere('correct_answer', 'like', "%{$search}%")
                        ->orWhereHas('options', function ($opt) use ($search) {
                            $opt->where('option_text', 'like', "%{$search}%");
                        });
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
                            'is_correct' => $optionData['is_correct'] ?? ($optionData['score'] > 0 ? true : false),
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
                                'is_correct' => $optionData['is_correct'] ?? ($optionData['score'] > 0 ? true : false),
                                'score' => $optionData['score'],
                                'option_label' => $optionData['label'] ?? null,
                            ]);
                        } else {
                            QuestionOption::create([
                                'question_id' => $question->id,
                                'option_text' => $optionData['option_text'],
                                'is_correct' => $optionData['is_correct'] ?? ($optionData['score'] > 0 ? true : false),
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
     * @param  array  $uploadedImages  Gambar yang sudah disimpan, key = nama file asli
     */
    public function importQuestions($file, string $examId, ?string $questionGroupId = null, array $uploadedImages = []): array
    {
        return DB::transaction(function () use ($file, $examId, $questionGroupId, $uploadedImages) {
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

                // Extract embedded images from any column
                $extractedImages = [];
                $drawings = $worksheet->getDrawingCollection();
                foreach ($drawings as $drawing) {
                    $coordinates = $drawing->getCoordinates();
                    preg_match('/([A-Z]+)(\d+)/', $coordinates, $matches);
                    if (count($matches) === 3) {
                        $col = $matches[1];
                        $row = $matches[2];

                        $imageContents = null;
                        $extension = 'png';
                        if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing) {
                            ob_start();
                            call_user_func($drawing->getRenderingFunction(), $drawing->getImageResource());
                            $imageContents = ob_get_contents();
                            ob_end_clean();
                            $extension = strtolower(str_replace('image/', '', $drawing->getMimeType()));
                            if ($extension === 'jpeg') $extension = 'jpg';
                        } elseif ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                            $imageContents = file_get_contents($drawing->getPath());
                            $extension = $drawing->getExtension();
                        }

                        if ($imageContents) {
                            $fileName = 'questions/' . date('Y/m') . '/' . uniqid() . '.' . $extension;
                            \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $imageContents);
                            $extractedImages[$row][$col] = $fileName;
                        }
                    }
                }

                $questions = [];
                $errors = [];
                $rowNumber = 1;

                foreach ($rows as $row) {
                    $rowNumber++;

                    // Validasi row data
                    if (count($row) < 2) {
                        $errors[] = "Baris $rowNumber: Data tidak lengkap";

                        continue;
                    }

                    $questionText      = isset($row[0]) ? trim($row[0]) : '';
                    $questionTypeLabel = isset($row[1]) ? trim($row[1]) : '';
                    $categoryId        = isset($row[2]) ? trim($row[2]) : null;
                    $score             = isset($row[3]) ? trim($row[3]) : null;
                    $correctAnswerLabel = isset($row[4]) ? trim($row[4]) : null;

                    // Map label bahasa Indonesia kembali ke system value
                    $typeMapping = [
                        'Pilihan Ganda' => 'single_choice',
                        'Checkbox (Banyak Pilihan)' => 'multiple_choice',
                        'Essay' => 'essay',
                        'Benar/Salah' => 'true_false',
                        // fallback jika user pake format lama
                        'single_choice' => 'single_choice',
                        'multiple_choice' => 'multiple_choice',
                        'essay' => 'essay',
                        'true_false' => 'true_false',
                    ];

                    $questionType = $typeMapping[$questionTypeLabel] ?? $questionTypeLabel;

                    if (empty($questionText) || empty($questionType)) {
                        $errors[] = "Baris $rowNumber: Teks soal dan tipe soal wajib diisi";

                        continue;
                    }

                    // Validasi tipe soal
                    if (! in_array($questionType, ['multiple_choice', 'essay', 'true_false', 'single_choice'])) {
                        $errors[] = "Baris $rowNumber: Tipe soal tidak valid";

                        continue;
                    }

                    // Handle grup soal (kolom F = index 5)
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

                    // Prioritaskan gambar yang di-embed langsung di Excel (dari $extractedImages)
                    $imagePath = $extractedImages[$rowNumber]['G'] ?? null;

                    // Fallback: Cocokkan gambar soal (kolom G = index 6) berdasarkan URUTAN upload
                    if (!$imagePath && isset($row[6]) && trim($row[6]) !== '' && trim($row[6]) !== '0') {
                        $imageIndex = (int) trim($row[6]) - 1; // konversi ke 0-based index
                        if ($imageIndex >= 0 && isset($uploadedImages[$imageIndex])) {
                            $imagePath = $uploadedImages[$imageIndex];
                        }
                    }

                    // Embed gambar ke dalam question_text sebagai <img> tag
                    // agar konsisten dengan tampilan di web (rich editor)
                    if ($imagePath) {
                        $imageUrl = asset('storage/' . $imagePath);
                        $questionText .= '<br><img src="' . $imageUrl . '" style="max-width:100%;height:auto;" />';
                        $imagePath = null; // tidak simpan di image_path karena sudah ada di question_text
                        unset($extractedImages[$rowNumber]['G']);
                    }

                    // Parse options untuk multiple choice (mulai kolom H = index 7)
                    $optionsData = [];
                    if ($questionType === 'multiple_choice' || $questionType === 'single_choice') {
                        $optionIndex = 7;
                        $optionLabel = 'A';
                        $maxOptionIndex = max(count($row), 17); // Cek setidaknya sampai kolom Q (index 16) untuk antisipasi image saja tanpa text

                        while ($optionIndex < $maxOptionIndex) {
                            $optionText = trim($row[$optionIndex] ?? '');
                            $optionScore = trim($row[$optionIndex + 1] ?? '0');
                            $optColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($optionIndex + 1);
                            $scoreColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($optionIndex + 2);
                            $prevColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($optionIndex);

                            // Cek gambar di kolom opsi, skor, atau kolom sebelumnya
                            $optImagePath = $extractedImages[$rowNumber][$optColLetter] ?? null;
                            if ($optImagePath) unset($extractedImages[$rowNumber][$optColLetter]);

                            if (!$optImagePath) {
                                $optImagePath = $extractedImages[$rowNumber][$scoreColLetter] ?? null;
                                if ($optImagePath) unset($extractedImages[$rowNumber][$scoreColLetter]);
                            }

                            if (!$optImagePath) {
                                $optImagePath = $extractedImages[$rowNumber][$prevColLetter] ?? null;
                                if ($optImagePath) unset($extractedImages[$rowNumber][$prevColLetter]);
                            }

                            if ($optionText === '' && !$optImagePath) {
                                // Lewati jika tidak ada teks dan tidak ada gambar, lanjut ke opsi berikutnya
                                $optionIndex += 2;
                                $optionLabel = chr(ord($optionLabel) + 1);
                                continue;
                            }

                            if ($optionScore === '') $optionScore = '0';
                            $isCorrect = (float)$optionScore > 0;

                            if ($optImagePath) {
                                $optImageUrl = asset('storage/' . $optImagePath);
                                if ($optionText !== '') {
                                    $optionText .= '<br>';
                                }
                                $optionText .= '<img src="' . $optImageUrl . '" style="max-width:100%;height:auto;" />';
                            }

                            $optionsData[] = [
                                'option_text' => $optionText,
                                'is_correct'  => $isCorrect,
                                'score'       => (int) $optionScore,
                                'label'       => $optionLabel,
                            ];

                            $optionIndex += 2;
                            $optionLabel = chr(ord($optionLabel) + 1);
                        }
                    }

                    // Hitung skor otomatis jika tidak diisi
                    if ($score === '') {
                        if ($questionType === 'single_choice' || $questionType === 'true_false') {
                            $score = empty($optionsData) ? 0 : max(array_column($optionsData, 'score'));
                        } else if ($questionType === 'multiple_choice') {
                            $score = array_sum(array_column($optionsData, 'score'));
                        } else {
                            $score = 0;
                        }
                    }

                    $questionData = [
                        'exam_id'           => $examId,
                        'question_group_id' => $finalQuestionGroupId,
                        'category_id'       => $categoryId,
                        'question_text'     => $questionText,
                        'question_type'     => $questionType,
                        'score'             => $score,
                        'correct_answer'    => $correctAnswerLabel,
                        'image_path'        => null, // gambar sudah di-embed di question_text
                        'options'           => $optionsData,
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
                    'imported'       => $questions,
                    'errors'         => $errors,
                    'total_imported' => count($questions),
                    'total_errors'   => count($errors),
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
                // if ($question->hasBeenUsedInExam()) {
                //     return $this->errorResponse('Soal tidak dapat dihapus karena sudah pernah digunakan dalam ujian yang sedang atau sudah berjalan.');
                // }

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

    /**
     * Delete multiple questions
     *
     * @param array $ids
     * @return array
     */
    public function deleteMultipleQuestions(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            try {
                $questions = Question::whereIn('id', $ids)->get();
                $deletedCount = 0;
                $skippedCount = 0;
                $groupIds = [];

                foreach ($questions as $question) {
                    // if ($question->hasBeenUsedInExam()) {
                    //     $skippedCount++;
                    //     continue;
                    // }

                    if ($question->question_group_id) {
                        $groupIds[] = $question->question_group_id;
                    }

                    $question->options()->delete();
                    $question->delete();
                    $deletedCount++;
                }

                // if ($deletedCount === 0 && $skippedCount > 0) {
                //     return $this->errorResponse('Semua soal yang dipilih tidak dapat dihapus karena sudah pernah digunakan dalam ujian.');
                // }

                // Update total questions for affected groups
                $groupIds = array_unique($groupIds);
                foreach ($groupIds as $groupId) {
                    $group = QuestionGroup::find($groupId);
                    if ($group) {
                        $group->updateTotalQuestions();
                    }
                }

                $message = "Berhasil menghapus $deletedCount soal.";
                // if ($skippedCount > 0) {
                //     $message .= " $skippedCount soal dilewati karena sudah pernah digunakan dalam ujian.";
                // }

                return $this->successResponse(null, $message);
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menghapus soal: ' . $e->getMessage());
            }
        });
    }

    /**
     * Generate and download Excel template for importing questions
     *
     * Kolom template:
     * A = Teks Soal | B = Tipe Soal | C = Kategori | D = Skor | E = Jawaban Benar
     * F = Grup Soal | G = Nomor Gambar (1-based index) | H+ = Opsi A, Skor A, Opsi B, Skor B, ...
     *
     * Kolom G diisi ANGKA (1, 2, 3...) yang sesuai urutan gambar yang di-upload via images[].
     * Tidak perlu tahu nama file — cukup upload gambar berurutan lalu tuliskan nomornya.
     *
     * Untuk gambar opsi jawaban, embed base64 langsung di teks opsi.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Soal');

        // ── Header Row ──────────────────────────────────────────────────────────
        $headers = [
            'A1' => 'Teks Soal (Wajib)',
            'B1' => 'Tipe Soal (Wajib)',
            'C1' => 'Kategori (Opsional)',
            'D1' => 'Skor Soal (Opsional)',
            'E1' => 'Jawaban Benar',
            'F1' => 'Grup Soal (Opsional)',
            'G1' => 'No. Gambar Soal (Opsional)',
            'H1' => 'Opsi A',
            'I1' => 'Skor A',
            'J1' => 'Opsi B',
            'K1' => 'Skor B',
            'L1' => 'Opsi C',
            'M1' => 'Skor C',
            'N1' => 'Opsi D',
            'O1' => 'Skor D',
            'P1' => 'Opsi E',
            'Q1' => 'Skor E',
        ];

        $headerStyle = [
            'font'    => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'    => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4A90D9'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        // Kolom gambar (G) diberi warna berbeda agar mudah dikenali
        $imageColStyle = [
            'font'    => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'    => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF27AE60'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $col = preg_replace('/\d/', '', $cell);
            $style = ($col === 'G') ? $imageColStyle : $headerStyle;
            $sheet->getStyle($cell)->applyFromArray($style);
        }

        // ── Data Validation ─────────────────────────────────────────────────────
        // Tipe soal
        $validationType = $sheet->getCell('B2')->getDataValidation();
        $validationType->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validationType->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $validationType->setAllowBlank(false);
        $validationType->setShowDropDown(true);
        $validationType->setFormula1('"Pilihan Ganda,Checkbox (Banyak Pilihan),Essay,Benar/Salah"');

        // Jawaban benar
        $validationAnswer = $sheet->getCell('E2')->getDataValidation();
        $validationAnswer->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validationAnswer->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $validationAnswer->setAllowBlank(true);
        $validationAnswer->setShowDropDown(true);
        $validationAnswer->setFormula1('"A,B,C,D,E"');

        // Grup soal (dari hidden sheet agar bisa >255 karakter)
        $groups = \App\Models\QuestionGroup::pluck('name')->toArray();
        if (! empty($groups)) {
            $groupSheet = $spreadsheet->createSheet();
            $groupSheet->setTitle('DataGrup');
            $groupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
            $row = 1;
            foreach ($groups as $groupName) {
                $groupSheet->setCellValue('A' . $row, $groupName);
                $row++;
            }

            $validationGroup = $sheet->getCell('F2')->getDataValidation();
            $validationGroup->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validationGroup->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $validationGroup->setAllowBlank(true);
            $validationGroup->setShowDropDown(true);
            $validationGroup->setFormula1('DataGrup!$A$1:$A$' . count($groups));

            for ($i = 2; $i <= 1000; $i++) {
                $sheet->getCell('F' . $i)->setDataValidation(clone $validationGroup);
            }
        }

        // Apply validation ke baris 2 s/d 1000
        for ($i = 2; $i <= 1000; $i++) {
            $sheet->getCell('B' . $i)->setDataValidation(clone $validationType);
            $sheet->getCell('E' . $i)->setDataValidation(clone $validationAnswer);
        }

        // Kolom G: validasi hanya boleh angka positif (nomor urut gambar)
        $validationImageNum = $sheet->getCell('G2')->getDataValidation();
        $validationImageNum->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_NONE);
        $validationImageNum->setShowInputMessage(true);
        $validationImageNum->setPromptTitle('Nomor Urut Gambar');
        $validationImageNum->setPrompt('Bisa ketik urutan gambar (1,2) ATAU langsung Copy-Paste/Insert gambar ke sel ini.');
        for ($i = 2; $i <= 1000; $i++) {
            $sheet->getCell('G' . $i)->setDataValidation(clone $validationImageNum);
        }

        // ── Contoh Data ──────────────────────────────────────────────────────────
        // Baris 2: multiple_choice dengan gambar soal
        $sheet->setCellValue('A2', 'Manakah gambar di bawah ini yang menunjukkan segitiga sama sisi?');
        $sheet->setCellValue('B2', 'Checkbox (Banyak Pilihan)');
        $sheet->setCellValue('C2', 'TWK');
        $sheet->setCellValue('D2', '5');
        $sheet->setCellValue('E2', 'A');
        $sheet->setCellValue('G2', 1);  // gambar pertama yang diupload (images[0])
        $sheet->setCellValue('H2', 'Gambar A');
        $sheet->setCellValue('I2', '5');
        $sheet->setCellValue('J2', 'Gambar B');
        $sheet->setCellValue('K2', '0');
        $sheet->setCellValue('L2', 'Gambar C');
        $sheet->setCellValue('M2', '0');

        // Baris 3: essay tanpa gambar
        $sheet->setCellValue('A3', 'Jelaskan pengertian dari Pancasila!');
        $sheet->setCellValue('B3', 'Essay');
        $sheet->setCellValue('C3', 'TIU');
        $sheet->setCellValue('D3', '10');

        // ── Keterangan di sheet terpisah ─────────────────────────────────────────
        $infoSheet = $spreadsheet->createSheet();
        $infoSheet->setTitle('Petunjuk');
        $infoSheet->setCellValue('A1', 'PETUNJUK PENGISIAN TEMPLATE');
        $infoSheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $infos = [
            ['Kolom', 'Keterangan'],
            ['A - Teks Soal', 'Teks pertanyaan. Wajib diisi.'],
            ['B - Tipe Soal', 'Pilih dari dropdown: Pilihan Ganda, Checkbox (Banyak Pilihan), Essay, Benar/Salah'],
            ['C - Kategori', 'Nama kategori soal. Opsional.'],
            ['D - Skor Soal', 'Nilai numerik untuk soal. Opsional, jika dikosongkan akan otomatis dihitung dari total skor opsi.'],
            ['E - Jawaban Benar', 'Label opsi yang benar (A/B/C/D/E). Wajib untuk pilihan ganda.'],
            ['F - Grup Soal', 'Pilih dari dropdown grup soal yang tersedia. Opsional.'],
            ['G - No. Gambar Soal', "Bisa ketik urutan gambar (1,2) ATAU langsung Copy-Paste/Insert gambar ke sel ini.\n" .
                "Kosongkan jika soal tidak memiliki gambar.\n" .
                "Contoh: soal baris 2 pakai gambar ke-1 → isi 1, soal baris 5 pakai gambar ke-3 → isi 3.\n" .
                "Saat upload, gambar di-attach sebagai images[] (array biasa, tanpa nama khusus)."],
            ['H,J,L,N,P - Opsi A,B,C,D,E', "Teks opsi jawaban. Bisa ketik teks, atau langsung Copy-Paste/Insert gambar ke sel opsi ini."],
            ['I,K,M,O,Q - Skor A,B,C,D,E', 'Nilai numerik tiap opsi. Tulis 0 untuk opsi yang salah.'],
        ];
        $infoSheet->fromArray($infos, null, 'A3');
        $infoSheet->getColumnDimension('A')->setWidth(35);
        $infoSheet->getColumnDimension('B')->setWidth(80);
        $infoSheet->getStyle('A3:B3')->getFont()->setBold(true);
        $infoSheet->getStyle('B3:B' . (count($infos) + 2))->getAlignment()->setWrapText(true);

        // ── Auto-size & freeze pane ──────────────────────────────────────────────
        $spreadsheet->setActiveSheetIndex(0);

        // Set fixed row height for better image UX
        for ($i = 2; $i <= 1000; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(60);
        }

        foreach (range('A', 'Q') as $col) {
            if ($col === 'G') {
                $sheet->getColumnDimension('G')->setAutoSize(false);
                $sheet->getColumnDimension('G')->setWidth(25);
            } else {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $sheet->freezePane('A2'); // freeze header row

        // ── Simpan ke file temp dan return sebagai download ──────────────────────
        $fileName = 'Template_Import_Soal_' . uniqid() . '.xlsx';
        $tempDir  = storage_path('app/public/temp');
        if (! \Illuminate\Support\Facades\File::exists($tempDir)) {
            \Illuminate\Support\Facades\File::makeDirectory($tempDir, 0755, true);
        }
        $tempPath = $tempDir . '/' . $fileName;

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        return response()->download($tempPath, 'Template_Import_Soal.xlsx')->deleteFileAfterSend(true);
    }
}
