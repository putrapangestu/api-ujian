<?php

namespace App\Services;

use App\Models\Student;
use App\Repositories\StudentRepositoryInterface;
use App\Repositories\StudentGroupRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Helpers\ResponseHelper;
use App\Models\UserExam;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Student Service Class
 *
 * Service ini menangani semua operasi terkait siswa
 * Termasuk fitur import dari Excel/CSV
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class StudentService extends BaseService
{
    /**
     * @var StudentRepositoryInterface
     */
    protected StudentRepositoryInterface $studentRepository;

    /**
     * @var StudentGroupRepositoryInterface
     */
    protected StudentGroupRepositoryInterface $studentGroupRepository;

    /**
     * @var UserRepositoryInterface
     */
    protected UserRepositoryInterface $userRepository;

    /**
     * StudentService constructor
     *
     * @param StudentRepositoryInterface $studentRepository
     * @param StudentGroupRepositoryInterface $studentGroupRepository
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(
        StudentRepositoryInterface $studentRepository,
        StudentGroupRepositoryInterface $studentGroupRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->studentRepository = $studentRepository;
        $this->studentGroupRepository = $studentGroupRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Get all students
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllStudents(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = Student::query()->with('group', 'user')
                ->whereHas('user');

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            if (isset($filters['group_id'])) {
                $query->where('group_id', $filters['group_id']);
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $students = $query->paginate($perPage);

            return ResponseHelper::paginate($students, 'Daftar siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar siswa: ' . $e->getMessage());
        }
    }

    /**
     * Get all students
     *
     * @param array $filters
     * @return array
     */
    public function getListAllStudents(array $filters = []): array
    {
        try {
            $students = $this->studentRepository->all(['*'], ['group']);

            return $this->successResponse($students, 'Daftar siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar siswa: ' . $e->getMessage());
        }
    }

    /**
     * Get student exam history with pagination and filters
     *
     * @param string $id
     * @param array $filters
     * @return array
     */
    public function getStudentExamHistory(string $id, array $filters): array
    {
        try {
            $student = $this->studentRepository->find($id);

            if (!$student) {
                return $this->errorResponse('Siswa tidak ditemukan', null, 404);
            }

            $query = \App\Models\UserExam::with(['exam'])
                ->where('student_id', $id)
                ->where('status', \App\Models\UserExam::STATUS_COMPLETED);

            // Filter by search (exam name)
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->whereHas('exam', function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                });
            }

            // Filter by start date
            if (!empty($filters['start_date'])) {
                $query->whereDate('start_time', '>=', $filters['start_date']);
            }

            // Filter by end date
            if (!empty($filters['end_date'])) {
                $query->whereDate('start_time', '<=', $filters['end_date']);
            }

            $perPage = $filters['per_page'] ?? 10;
            $userExams = $query->orderBy('start_time', 'desc')->paginate($perPage);

            // Mapping data to format expected by FE
            $mappedData = [];
            foreach ($userExams as $userExam) {
                // Calculate category scores manually based on answers
                $scoreTwk = 0;
                $scoreTiu = 0;
                $scoreTkp = 0;

                // Load answers with question category info
                $userExam->load(['userAnswers.question.category']);

                foreach ($userExam->userAnswers as $answer) {
                    $categoryCode = strtolower($answer->question?->category?->code ?? '');

                    if (str_contains($categoryCode, 'twk')) {
                        $scoreTwk += $answer->score;
                    } elseif (str_contains($categoryCode, 'tiu')) {
                        $scoreTiu += $answer->score;
                    } elseif (str_contains($categoryCode, 'tkp')) {
                        $scoreTkp += $answer->score;
                    }
                }

                $mappedData[] = [
                    'exam_history_id' => $userExam->id,
                    'exam_id' => $userExam->exam_id,
                    'exam_name' => $userExam->exam->title ?? 'Unknown Exam',
                    'start_time' => $userExam->start_time,
                    'end_time' => $userExam->end_time,
                    'status' => $userExam->status,
                    'is_passed' => $userExam->score >= ($userExam->exam->passing_grade ?? 0),
                    'total_score' => $userExam->score ?? 0,
                    'score_twk' => $scoreTwk,
                    'score_tiu' => $scoreTiu,
                    'score_tkp' => $scoreTkp,
                ];
            }

            return $this->successResponse([
                'data' => $mappedData,
                'pagination' => [
                    'current_page' => $userExams->currentPage(),
                    'per_page' => $userExams->perPage(),
                    'total_items' => $userExams->total(),
                    'total_pages' => $userExams->lastPage(),
                ]
            ], 'Riwayat ujian berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil riwayat ujian: ' . $e->getMessage());
        }
    }

    public function getStudentExamSummary(string $id) {
        try {
            $userExam = UserExam::selectRaw('
                count(*) as total_exam,
                SUM(score) as total_score,
            ')
            ->where('student_id', $id)
            ->get();

            return $this->successResponse($userExam, "Berhasil mengambil summary ujian");
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil summary ujian: ' . $e->getMessage());
        }
    }

    /**
     * Export student exam history to Excel
     *
     * @param string $id
     * @param array $filters
     * @return array
     */
    public function exportStudentExamHistory(string $id, array $filters): array
    {
        try {
            $student = $this->studentRepository->find($id);

            if (!$student) {
                return $this->errorResponse('Siswa tidak ditemukan', null, 404);
            }

            $query = \App\Models\UserExam::with(['exam'])
                ->where('student_id', $id)
                ->where('status', \App\Models\UserExam::STATUS_COMPLETED);

            // Filter by search (exam name)
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->whereHas('exam', function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                });
            }

            // Filter by start date
            if (!empty($filters['start_date'])) {
                $query->whereDate('start_time', '>=', $filters['start_date']);
            }

            // Filter by end date
            if (!empty($filters['end_date'])) {
                $query->whereDate('start_time', '<=', $filters['end_date']);
            }

            $userExams = $query->orderBy('start_time', 'desc')->get();

            // Create Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Riwayat Ujian');

            // Set headers
            $headers = [
                'A1' => 'Nama Ujian',
                'B1' => 'Tanggal Mulai',
                'C1' => 'Tanggal Selesai',
                // 'D1' => 'Status Kelulusan',
                'D1' => 'Total Skor',
                'E1' => 'Skor TWK',
                'F1' => 'Skor TIU',
                'G1' => 'Skor TKP',
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
            }

            // Set data
            $row = 2;
            foreach ($userExams as $userExam) {
                $scoreTwk = 0;
                $scoreTiu = 0;
                $scoreTkp = 0;

                // Load answers with question category info
                $userExam->load(['userAnswers.question.category']);

                foreach ($userExam->userAnswers as $answer) {
                    $categoryCode = strtolower($answer->question?->category?->code ?? '');

                    if (str_contains($categoryCode, 'twk')) {
                        $scoreTwk += $answer->score;
                    } elseif (str_contains($categoryCode, 'tiu')) {
                        $scoreTiu += $answer->score;
                    } elseif (str_contains($categoryCode, 'tkp')) {
                        $scoreTkp += $answer->score;
                    }
                }

                $isPassed = $userExam->score >= ($userExam->exam->passing_grade ?? 0);
                $statusKelulusan = $isPassed ? 'Lulus' : 'Tidak Lulus';

                $sheet->setCellValue('A' . $row, $userExam->exam->title ?? 'Unknown Exam');
                $sheet->setCellValue('B' . $row, $userExam->start_time ? $userExam->start_time->format('Y-m-d H:i:s') : '-');
                $sheet->setCellValue('C' . $row, $userExam->end_time ? $userExam->end_time->format('Y-m-d H:i:s') : '-');
                // $sheet->setCellValue('D' . $row, $statusKelulusan);
                $sheet->setCellValue('D' . $row, $userExam->score ?? 0);
                $sheet->setCellValue('E' . $row, $scoreTwk);
                $sheet->setCellValue('F' . $row, $scoreTiu);
                $sheet->setCellValue('G' . $row, $scoreTkp);

                $row++;
            }

            // Auto size columns
            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create file
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $student->name);
            $filename = 'Riwayat_Ujian_' . $safeName . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filePath = 'exports/' . $filename;
            // Saved to public disk to make it accessible via URL
            $fullPath = storage_path('app/public/' . $filePath);

            // Ensure directory exists
            if (!file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($fullPath);

            return $this->successResponse([
                'file_path' => $filePath,
                'file_name' => $filename,
                'download_url' => asset('storage/' . $filePath)
            ], 'Export riwayat ujian berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal export riwayat ujian: ' . $e->getMessage());
        }
    }
    public function getStudentById(string $id): array
    {
        try {
            $student = $this->studentRepository->find($id, ['group', 'userExams.exam'], ['*']);

            if (!$student) {
                return $this->errorResponse('Siswa tidak ditemukan', null, 404);
            }

            // Append additional statistics for UX
            $completedExams = $student->userExams->where('status', \App\Models\UserExam::STATUS_COMPLETED);
            $totalExamCompleted = $completedExams->count();

            $averageScore = 0;
            if ($totalExamCompleted > 0) {
                $averageScore = round($completedExams->avg('score') ?? 0, 2);
            }

            $studentData = $student->toArray();
            $studentData['total_exam_completed'] = $totalExamCompleted;
            $studentData['average_score'] = $averageScore;

            return $this->successResponse($studentData, 'Detail siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil detail siswa: ' . $e->getMessage());
        }
    }

    /**
     * Create new student
     *
     * @param array $data Validated data from StudentRequest
     * @return array
     */
    public function createStudent(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                // Check if group exists and has available slots
                if (isset($data['group_id'])) {
                    $group = $this->studentGroupRepository->find($data['group_id']);
                    if (!$group) {
                        return $this->errorResponse('Grup tidak ditemukan', null, 404);
                    }

                    if (!$group->hasAvailableSlots()) {
                        return $this->errorResponse('Grup sudah penuh');
                    }
                }

                // Create student
                $user = $this->userRepository->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => bcrypt($data['birth_date']),
                ]);

                // Add user_id to student data
                $data['user_id'] = $user->id;

                $student = $this->studentRepository->create($data);

                // assign role student
                $user->assignRole('student');

                return $this->successResponse($student, 'Siswa berhasil dibuat', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal membuat siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Update student
     *
     * @param string $id
     * @param array $data Validated data from StudentRequest
     * @return array
     */
    public function updateStudent(string $id, array $data): array
    {
        // Log::info('Update student', ['id' => $id, 'data' => $data]);
        return DB::transaction(function () use ($id, $data) {
            try {
                // Extract user data from payload
                $payloadUser = [];
                if (isset($data['name'])) {
                    $payloadUser['name'] = $data['name'];
                }
                if (isset($data['email'])) {
                    $payloadUser['email'] = $data['email'];
                    unset($data['email']);
                }

                // Check if student exists
                $student = $this->studentRepository->find($id);
                if (!$student) {
                    return $this->errorResponse('Siswa tidak ditemukan', null, 404);
                }

                // Check if group exists and has available slots if changing group
                if (isset($data['group_id']) && $data['group_id'] != $student->group_id) {
                    $newGroup = $this->studentGroupRepository->find($data['group_id']);
                    if (!$newGroup) {
                        return $this->errorResponse('Grup baru tidak ditemukan', null, 404);
                    }

                    if (!$newGroup->hasAvailableSlots()) {
                        return $this->errorResponse('Grup baru sudah penuh');
                    }
                }

                // Update user if changed
                if (isset($data['birth_date']) && $student->birth_date !== $data['birth_date']) {
                    $payloadUser['password'] = bcrypt(Carbon::parse($data['birth_date'])->format('DDMMYYYY'));
                }

                if (!empty($payloadUser)) {
                    $this->userRepository->update($student->user_id, $payloadUser);
                }

                // Update student
                $updated = $this->studentRepository->update($id, $data);

                if (!$updated) {
                    return $this->errorResponse('Gagal update siswa');
                }

                // Get updated student
                $updatedStudent = $this->studentRepository->find($id);

                return $this->successResponse($updatedStudent, 'Siswa berhasil diupdate');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal update siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Delete student
     *
     * @param string $id
     * @return array
     */
    public function deleteStudent(string $id): array
    {
        return DB::transaction(function () use ($id) {
            try {
                // Check if student exists
                $student = $this->studentRepository->find($id);
                if (!$student) {
                    return $this->errorResponse('Siswa tidak ditemukan', null, 404);
                }

                // // Check if student has exam history
                // if ($student->userExams()->exists()) {
                //     return $this->errorResponse('Siswa tidak dapat dihapus karena memiliki riwayat ujian');
                // }

                // Delete student
                $this->userRepository->delete($student->user_id);

                $deleted = $this->studentRepository->delete($id);

                if (!$deleted) {
                    return $this->errorResponse('Gagal menghapus siswa');
                }

                return $this->successResponse(null, 'Siswa berhasil dihapus');
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menghapus siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Delete multiple students
     *
     * @param array $studentIds
     * @return array
     */
    public function deleteMultipleStudents(array $studentIds): array
    {
        return DB::transaction(function () use ($studentIds) {
            try {
                // Check if any student has exam history
                // $studentsWithExams = Student::whereIn('id', $studentIds)->whereHas('userExams')->exists();
                // if ($studentsWithExams) {
                //     return $this->errorResponse('Beberapa siswa tidak dapat dihapus karena memiliki riwayat ujian');
                // }

                $students = Student::whereIn('id', $studentIds)->get();
                $deletedCount = 0;

                foreach ($students as $student) {
                    // Delete associated user
                    if ($student->user_id) {
                        $this->userRepository->delete($student->user_id);
                    }

                    // Delete student
                    $this->studentRepository->delete($student->id);
                    $deletedCount++;
                }

                return $this->successResponse(
                    ['deleted_count' => $deletedCount],
                    "{$deletedCount} siswa berhasil dihapus"
                );

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menghapus beberapa siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Generate and download Excel template for importing students
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set column headers based on import logic
        $headers = [
            'A1' => 'Nama Lengkap (Wajib)',
            'B1' => 'Tanggal Lahir (Wajib, Format: DDMMYYYY)',
            'C1' => 'Asal Sekolah (Wajib)',
            'D1' => 'No Telp (Opsional)'
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');
        }

        // Add Example Data
        $sheet->setCellValue('A2', 'John Doe');
        $sheet->setCellValue('B2', '15082000');
        $sheet->setCellValue('C2', 'SMAN 1 Jakarta');
        $sheet->setCellValue('D2', '081234567890');

        // Auto-size columns
        foreach (range('A', 'D') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Save to temporary file
        $fileName = 'Template_Import_Siswa.xlsx';
        $tempPath = storage_path('app/temp/' . $fileName);

        if (!\Illuminate\Support\Facades\File::exists(storage_path('app/temp'))) {
            \Illuminate\Support\Facades\File::makeDirectory(storage_path('app/temp'), 0755, true);
        }

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Import students from Excel/CSV file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $groupId
     * @return array
     */
    public function importStudents($file, string $groupId): array
    {
        return DB::transaction(function () use ($file, $groupId) {
            try {
                // Validate group
                $group = $this->studentGroupRepository->find($groupId);
                if (!$group) {
                    return $this->errorResponse('Grup tidak ditemukan', null, 404);
                }

                // Process file
                $fileName = $file->getClientOriginalName();
                $filePath = $file->storeAs('temp', 'import_student_' . uniqid() . '_' . $fileName, 'public');
                $fileFullPath = storage_path('app/public/' . $filePath);

                // Read Excel file
                $spreadsheet = IOFactory::load($fileFullPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Remove header row
                array_shift($rows);

                $students = [];
                $errors = [];
                $rowNumber = 1;

                foreach ($rows as $row) {
                    $rowNumber++;

                    // Validate row data
                    if (count($row) < 3) {
                        $errors[] = "Baris $rowNumber: Data tidak lengkap";
                        continue;
                    }

                    $name = trim($row[0] ?? '');
                    $birthDateRaw = trim($row[1] ?? '');
                    if (strlen($birthDateRaw) == 7) {
                        $birthDateRaw = '0' . $birthDateRaw;
                    }

                    $address = trim($row[2] ?? '');
                    $phone = trim($row[3] ?? '');

                    if (empty($name) || empty($birthDateRaw) || empty($address)) {
                        $errors[] = "Baris $rowNumber: Nama, Tanggal Lahir, dan Asal Sekolah wajib diisi";
                        continue;
                    }

                    // Format tanggal lahir to YYYY-MM-DD
                    $birthDateFormatted = null;
                    $passwordString = null;

                    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $birthDateRaw, $matches) || preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birthDateRaw, $matches)) {
                        // Input: DD-MM-YYYY or DD/MM/YYYY
                        $birthDateFormatted = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                        // Log::info("Baris check 1 $rowNumber: Tanggal Lahir $birthDateRaw diubah menjadi $matches[3]-$matches[2]-$matches[1]");
                        $passwordString = $matches[1] . $matches[2] . $matches[3]; // DDMMYYYY
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDateRaw)) {
                        // Input: YYYY-MM-DD
                        $birthDateFormatted = $birthDateRaw;
                        $parts = explode('-', $birthDateRaw);
                        // Log::info("Baris check 2 $rowNumber: Tanggal Lahir $birthDateRaw diubah menjadi $parts[2]-$parts[1]-$parts[0]");
                        $passwordString = $parts[2] . $parts[1] . $parts[0]; // DDMMYYYY
                    } else if (preg_match('/^\d{8}$/', $birthDateRaw)) {
                        // Input: DDMMYYYY
                        $day = substr($birthDateRaw, 0, 2);
                        $month = substr($birthDateRaw, 2, 2);
                        $year = substr($birthDateRaw, 4, 4);
                        // Log::info("Baris check 3 $rowNumber: Tanggal Lahir $birthDateRaw diubah menjadi $year-$month-$day");
                        $birthDateFormatted = $year . '-' . $month . '-' . $day;
                        $passwordString = $birthDateRaw; // DDMMYYYY
                    } else {
                        $errors[] = "Baris $rowNumber: Format tanggal lahir tidak valid (gunakan DD-MM-YYYY atau YYYY-MM-DD atau YYYYMMDD)";
                        continue;
                    }

                    // Generate email
                    $baseEmail = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
                    $email = $baseEmail . '@bimbel.com';

                    // Generate NISN (sebagai unique identifier jika diperlukan)
                    $nisn = 'NISN' . date('Ymdhis') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

                    $students[] = [
                        'nisn' => $nisn,
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'address' => $address,
                        'group_id' => $groupId,
                        'status' => \App\Models\Student::STATUS_ACTIVE,
                        'birth_date' => $birthDateFormatted,
                        'password_string' => $passwordString,
                    ];
                }

                // Import students
                $result = $this->studentRepository->importStudents($students);

                // Clean up file
                Storage::disk('public')->delete($filePath);

                return $this->successResponse([
                    'imported' => $result['imported'],
                    'errors' => array_merge($errors, $result['errors']),
                    'total_imported' => $result['total_imported'],
                    'total_errors' => count($errors) + $result['total_errors']
                ], 'Import siswa selesai');

            } catch (\Exception $e) {
                DB::rollBack();
                if (isset($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
                return $this->errorResponse('Gagal import siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Export students to Excel
     *
     * @param array $filters
     * @return array
     */
    public function exportStudents(array $filters = []): array
    {
        try {
            $query = Student::query()->with(['group', 'user'])
                    ->whereHas('user');

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('email', 'like', "%{$search}%");
                        });
                });
            }

            if (isset($filters['group_id'])) {
                $query->where('group_id', $filters['group_id']);
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            // Batasi jumlah row yang diekspor agar tidak OOM
            $students = $query->limit(5000)->get();

            if ($students->isEmpty()) {
                return $this->errorResponse('Tidak ada data siswa untuk diekspor', null, 404);
            }

            // Create Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Data Siswa');

            // Set headers
            $headers = [
                'A1' => 'No',
                'B1' => 'Username',
                'C1' => 'Asal Sekolah',
                'D1' => 'Password'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
            }

            // Set data
            $row = 2;
            $no = 1;
            foreach ($students as $student) {
                $sheet->setCellValue('A' . $row, $no);
                $sheet->setCellValue('B' . $row, strtolower(str_replace(' ', '', $student->name)));
                $sheet->setCellValue('C' . $row, $student->address);

                $birthDateRaw = str_replace("-", "", $student->birth_date);
                $year = substr($birthDateRaw, 0, 4);
                $month = substr($birthDateRaw, 4, 2);
                $day = substr($birthDateRaw, 6, 2);

                // Handle birth date safely
                $birthDate = $student->birth_date ? $day . $month . $year : "";
                $sheet->setCellValue('D' . $row, $birthDate);

                $row++;
                $no++;
            }

            // Auto size columns
            foreach (range('A', 'D') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create temporary file
            $filename = 'Data_Siswa_' . date('Ymd_His') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $filename);

            // Ensure directory exists
            if (!\Illuminate\Support\Facades\File::exists(storage_path('app/temp'))) {
                \Illuminate\Support\Facades\File::makeDirectory(storage_path('app/temp'), 0755, true);
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($tempPath);

            return [
                'success' => true,
                'file_path' => $tempPath,
                'file_name' => $filename
            ];

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal export siswa: ' . $e->getMessage());
        }
    }
}
