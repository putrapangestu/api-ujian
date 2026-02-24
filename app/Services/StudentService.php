<?php

namespace App\Services;

use App\Models\Student;
use App\Repositories\StudentRepositoryInterface;
use App\Repositories\StudentGroupRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
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
     * @return array
     */
    public function getAllStudents(array $filters = []): array
    {
        try {
            $students = $this->studentRepository->all(['*'], ['group']);

            return $this->successResponse($students, 'Daftar siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar siswa: ' . $e->getMessage());
        }
    }

    /**
     * Get student by ID
     *
     * @param int $id
     * @return array
     */
    public function getStudentById(int $id): array
    {
        try {
            $student = $this->studentRepository->find($id, ['group', 'userExams.exam'], ['*']);

            if (!$student) {
                return $this->errorResponse('Siswa tidak ditemukan', null, 404);
            }

            return $this->successResponse($student, 'Detail siswa berhasil diambil');

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
                if(isset($data['group_id'])) {
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
                $student->assignRole('student');

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
     * @param int $id
     * @param array $data Validated data from StudentRequest
     * @return array
     */
    public function updateStudent(int $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            try {
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

                // update user email if changed
                $this->userRepository->update($student->user_id, ['email' => $data['email'], 'name' => $data['name']]);

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
     * @param int $id
     * @return array
     */
    public function deleteStudent(int $id): array
    {
        return DB::transaction(function () use ($id) {
            try {
                // Check if student exists
                $student = $this->studentRepository->find($id);
                if (!$student) {
                    return $this->errorResponse('Siswa tidak ditemukan', null, 404);
                }

                // Check if student has exam history
                if ($student->userExams()->exists()) {
                    return $this->errorResponse('Siswa tidak dapat dihapus karena memiliki riwayat ujian');
                }

                // Delete student
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
     * Import students from Excel/CSV file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $groupId
     * @return array
     */
    public function importStudents($file, int $groupId): array
    {
        return DB::transaction(function () use ($file, $groupId) {
            try {
                // Validate group
                $group = $this->studentGroupRepository->find($groupId);
                if (!$group) {
                    return $this->errorResponse('Grup tidak ditemukan', null, 404);
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

                    $nisn = trim($row[0]);
                    $name = trim($row[1]);
                    $email = trim($row[2]);
                    $phone = trim($row[3] ?? '');
                    $address = trim($row[4] ?? '');

                    if (empty($nisn) || empty($name) || empty($email)) {
                        $errors[] = "Baris $rowNumber: NISN, nama, dan email wajib diisi";
                        continue;
                    }

                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Baris $rowNumber: Format email tidak valid";
                        continue;
                    }

                    $students[] = [
                        'nisn' => $nisn,
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'address' => $address,
                        'group_id' => $groupId,
                        'status' => Student::STATUS_ACTIVE,
                    ];
                }

                // Import students
                $result = $this->studentRepository->importStudents($students);

                // Clean up file
                Storage::delete($filePath);

                return $this->successResponse([
                    'imported' => $result['imported'],
                    'errors' => array_merge($errors, $result['errors']),
                    'total_imported' => $result['total_imported'],
                    'total_errors' => count($errors) + $result['total_errors']
                ], 'Import siswa selesai');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal import siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Export students to Excel
     *
     * @param int $groupId
     * @return array
     */
    public function exportStudents(int $groupId): array
    {
        try {
            $group = $this->studentGroupRepository->find($groupId);
            if (!$group) {
                return $this->errorResponse('Grup tidak ditemukan', null, 404);
            }

            $students = $this->studentRepository->getByGroup($groupId);

            // Create Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'NISN');
            $sheet->setCellValue('B1', 'Nama');
            $sheet->setCellValue('C1', 'Email');
            $sheet->setCellValue('D1', 'Telepon');
            $sheet->setCellValue('E1', 'Alamat');
            $sheet->setCellValue('F1', 'Status');

            // Set data
            $row = 2;
            foreach ($students as $student) {
                $sheet->setCellValue('A' . $row, $student->nisn);
                $sheet->setCellValue('B' . $row, $student->name);
                $sheet->setCellValue('C' . $row, $student->email);
                $sheet->setCellValue('D' . $row, $student->phone ?? '');
                $sheet->setCellValue('E' . $row, $student->address ?? '');
                $sheet->setCellValue('F' . $row, $student->status);
                $row++;
            }

            // Auto size columns
            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create file
            $filename = 'students_' . $group->name . '_' . date('Y-m-d_H-i-s') . '.xlsx';
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
                'download_url' => asset('storage/' . $filePath)
            ], 'Export siswa berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal export siswa: ' . $e->getMessage());
        }
    }
}
