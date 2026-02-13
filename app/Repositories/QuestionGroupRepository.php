<?php

namespace App\Repositories;

use App\Models\QuestionGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * QuestionGroup Repository Class
 * 
 * Repository ini menangani semua operasi database terkait QuestionGroup model
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
class QuestionGroupRepository extends BaseRepository implements QuestionGroupRepositoryInterface
{
    /**
     * QuestionGroupRepository constructor
     * 
     * @param QuestionGroup $model
     */
    public function __construct(QuestionGroup $model)
    {
        parent::__construct($model);
    }

    /**
     * Get active question groups
     * 
     * @return Collection
     */
    public function getActiveGroups(): Collection
    {
        return $this->model->where('status', QuestionGroup::STATUS_ACTIVE)->get();
    }

    /**
     * Get question groups with question count
     * 
     * @return Collection
     */
    public function getWithQuestionCount(): Collection
    {
        return $this->model->withCount('questions')->get();
    }

    /**
     * Find question group by code
     * 
     * @param string $code
     * @return QuestionGroup|null
     */
    public function findByCode(string $code): ?QuestionGroup
    {
        return $this->model->where('code', $code)->first();
    }

    /**
     * Search question groups by name or code
     * 
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection
    {
        return $this->model
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Get question groups for exam
     * 
     * @param int $examId
     * @return Collection
     */
    public function getForExam(int $examId): Collection
    {
        return $this->model
            ->whereHas('exams', function ($query) use ($examId) {
                $query->where('exam_id', $examId);
            })
            ->with(['exams' => function ($query) use ($examId) {
                $query->where('exam_id', $examId);
            }])
            ->get();
    }

    /**
     * Import question group from file
     * 
     * @param array $data
     * @return array
     */
    public function importGroup(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $imported = [];
            $errors = [];

            foreach ($data as $index => $groupData) {
                try {
                    // Validasi data
                    if (empty($groupData['name']) || empty($groupData['code'])) {
                        $errors[] = "Baris " . ($index + 1) . ": Nama dan kode wajib diisi";
                        continue;
                    }

                    // Cek duplikasi kode
                    if ($this->findByCode($groupData['code'])) {
                        $errors[] = "Baris " . ($index + 1) . ": Kode sudah digunakan";
                        continue;
                    }

                    // Create group
                    $group = $this->create($groupData);
                    $imported[] = $group;

                } catch (\Exception $e) {
                    $errors[] = "Baris " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            return [
                'imported' => $imported,
                'errors' => $errors,
                'total_imported' => count($imported),
                'total_errors' => count($errors)
            ];
        });
    }
}