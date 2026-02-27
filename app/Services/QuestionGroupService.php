<?php

namespace App\Services;

use App\Models\QuestionGroup;
use App\Repositories\QuestionGroupRepositoryInterface;
use App\Helpers\ResponseHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * QuestionGroup Service Class
 *
 * Service ini menangani semua operasi terkait grup soal/bank soal
 * Termasuk fitur import dari Excel/CSV
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class QuestionGroupService extends BaseService
{
    /**
     * @var QuestionGroupRepositoryInterface
     */
    protected QuestionGroupRepositoryInterface $questionGroupRepository;

    /**
     * QuestionGroupService constructor
     *
     * @param QuestionGroupRepositoryInterface $questionGroupRepository
     */
    public function __construct(QuestionGroupRepositoryInterface $questionGroupRepository)
    {
        $this->questionGroupRepository = $questionGroupRepository;
    }

    /**
     * Get all question groups (paginated)
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllGroups(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = QuestionGroup::query()
            ->with('questionCategory')
            ->withCount('questions');

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $groups = $query->paginate($perPage);

            return ResponseHelper::paginate($groups, 'Daftar grup soal berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar grup soal: ' . $e->getMessage());
        }
    }

    /**
     * Get all question groups without pagination
     *
     * @param array $filters
     * @return array
     */
    public function getAllGroupsList(array $filters = []): array
    {
        try {
            $query = QuestionGroup::query()->withCount('questions');

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $groups = $query->get();

            return $this->successResponse($groups, 'Daftar grup soal berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar grup soal: ' . $e->getMessage());
        }
    }

    /**
     * Get question group by ID
     *
     * @param string $id
     * @return array
     */
    public function getGroupById(string $id): array
    {
        try {
            $group = $this->questionGroupRepository->find($id, ['questions']);

            if (!$group) {
                return $this->errorResponse('Grup soal tidak ditemukan', null, 404);
            }

            return $this->successResponse($group, 'Detail grup soal berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil detail grup soal: ' . $e->getMessage());
        }
    }

    /**
     * Create new question group
     *
     * @param array $data Validated data from QuestionGroupRequest
     * @return array
     */
    public function createGroup(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                $group = $this->questionGroupRepository->create($data);

                return $this->successResponse($group, 'Grup soal berhasil dibuat', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal membuat grup soal: ' . $e->getMessage());
            }
        });
    }

    /**
     * Update question group
     *
     * @param string $id
     * @param array $data Validated data from QuestionGroupRequest
     * @return array
     */
    public function updateGroup(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            try {
                // Check if group exists
                $group = $this->questionGroupRepository->find($id);
                if (!$group) {
                    return $this->errorResponse('Grup soal tidak ditemukan', null, 404);
                }

                // Update group
                $updated = $this->questionGroupRepository->update($id, $data);

                if (!$updated) {
                    return $this->errorResponse('Gagal update grup soal');
                }

                // Get updated group
                $updatedGroup = $this->questionGroupRepository->find($id);

                return $this->successResponse($updatedGroup, 'Grup soal berhasil diupdate');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal update grup soal: ' . $e->getMessage());
            }
        });
    }

    /**
     * Delete question group
     *
     * @param string $id
     * @return array
     */
    public function deleteGroup(string $id): array
    {
        return DB::transaction(function () use ($id) {
            try {
                // Check if group exists
                $group = $this->questionGroupRepository->find($id);
                if (!$group) {
                    return $this->errorResponse('Grup soal tidak ditemukan', null, 404);
                }

                // Check if group has questions
                if ($group->questions()->exists()) {
                    return $this->errorResponse('Grup soal tidak dapat dihapus karena masih memiliki soal');
                }

                // Check if group is used in exams
                if ($group->exams()->exists()) {
                    return $this->errorResponse('Grup soal tidak dapat dihapus karena masih digunakan dalam ujian');
                }

                // Delete group
                $deleted = $this->questionGroupRepository->delete($id);

                if (!$deleted) {
                    return $this->errorResponse('Gagal menghapus grup soal');
                }

                return $this->successResponse(null, 'Grup soal berhasil dihapus');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menghapus grup soal: ' . $e->getMessage());
            }
        });
    }

    /**
     * Import question groups from Excel file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    public function importGroups($file): array
    {
        return DB::transaction(function () use ($file) {
            try {
                // Process file
                $filePath = $file->store('temp');
                $fileFullPath = storage_path('app/' . $filePath);

                // Read Excel file
                $spreadsheet = IOFactory::load($fileFullPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Remove header row
                array_shift($rows);

                $groups = [];
                $errors = [];
                $rowNumber = 1;

                foreach ($rows as $row) {
                    $rowNumber++;

                    // Validasi row data
                    if (count($row) < 2) {
                        $errors[] = "Baris $rowNumber: Data tidak lengkap";
                        continue;
                    }

                    $name = trim($row[0]);
                    $code = trim($row[1]);
                    $description = trim($row[2] ?? '');
                    $status = trim($row[3] ?? 'draft');

                    if (empty($name) || empty($code)) {
                        $errors[] = "Baris $rowNumber: Nama dan kode wajib diisi";
                        continue;
                    }

                    // Validasi status
                    if (!in_array($status, ['active', 'inactive', 'draft'])) {
                        $status = 'draft';
                    }

                    $groups[] = [
                        'name' => $name,
                        'code' => $code,
                        'description' => $description,
                        'status' => $status,
                        'total_questions' => 0,
                    ];
                }

                // Import groups
                $result = $this->questionGroupRepository->importGroup($groups);

                // Clean up file
                Storage::delete($filePath);

                return $this->successResponse([
                    'imported' => $result['imported'],
                    'errors' => array_merge($errors, $result['errors']),
                    'total_imported' => $result['total_imported'],
                    'total_errors' => count($errors) + $result['total_errors']
                ], 'Import grup soal selesai');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal import grup soal: ' . $e->getMessage());
            }
        });
    }

    /**
     * Export question groups to Excel
     *
     * @return array
     */
    public function exportGroups(): array
    {
        try {
            $groups = $this->questionGroupRepository->getWithQuestionCount();

            // Create Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'Nama Grup');
            $sheet->setCellValue('B1', 'Kode');
            $sheet->setCellValue('C1', 'Deskripsi');
            $sheet->setCellValue('D1', 'Jumlah Soal');
            $sheet->setCellValue('E1', 'Status');

            // Set data
            $row = 2;
            foreach ($groups as $group) {
                $sheet->setCellValue('A' . $row, $group->name);
                $sheet->setCellValue('B' . $row, $group->code);
                $sheet->setCellValue('C' . $row, $group->description ?? '');
                $sheet->setCellValue('D' . $row, $group->questions_count);
                $sheet->setCellValue('E' . $row, $group->status);
                $row++;
            }

            // Auto size columns
            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create file
            $filename = 'question_groups_' . date('Y-m-d_H-i-s') . '.xlsx';
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
            ], 'Export grup soal berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal export grup soal: ' . $e->getMessage());
        }
    }
}
