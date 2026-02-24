<?php

namespace App\Services;

use App\Models\QuestionCategory;
use App\Repositories\QuestionCategoryRepositoryInterface;
use App\Helpers\ResponseHelper;
use Illuminate\Support\Facades\DB;

/**
 * QuestionCategory Service Class
 *
 * Service ini menangani semua operasi terkait kategori soal
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class QuestionCategoryService extends BaseService
{
    /**
     * @var QuestionCategoryRepositoryInterface
     */
    protected QuestionCategoryRepositoryInterface $questionCategoryRepository;

    /**
     * QuestionCategoryService constructor
     *
     * @param QuestionCategoryRepositoryInterface $questionCategoryRepository
     */
    public function __construct(QuestionCategoryRepositoryInterface $questionCategoryRepository)
    {
        $this->questionCategoryRepository = $questionCategoryRepository;
    }

    /**
     * Get all question categories (paginated)
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllCategories(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = QuestionCategory::query()->withCount('questions');

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

            $categories = $query->paginate($perPage);

            return ResponseHelper::paginate($categories, 'Daftar kategori soal berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar kategori soal: ' . $e->getMessage());
        }
    }

    /**
     * Get all question categories without pagination
     *
     * @param array $filters
     * @return array
     */
    public function getAllCategoriesList(array $filters = []): array
    {
        try {
            $query = QuestionCategory::query()->withCount('questions');

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

            $categories = $query->get();

            return $this->successResponse($categories, 'Daftar kategori soal berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar kategori soal: ' . $e->getMessage());
        }
    }

    /**
     * Get question category by ID
     *
     * @param string $id
     * @return array
     */
    public function getCategoryById(string $id): array
    {
        try {
            $category = $this->questionCategoryRepository->find($id, ['questions']);

            if (!$category) {
                return $this->errorResponse('Kategori soal tidak ditemukan', null, 404);
            }

            return $this->successResponse($category, 'Detail kategori soal berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil detail kategori soal: ' . $e->getMessage());
        }
    }

    /**
     * Create new question category
     *
     * @param array $data Validated data from QuestionCategoryRequest
     * @return array
     */
    public function createCategory(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                $category = $this->questionCategoryRepository->create($data);

                return $this->successResponse($category, 'Kategori soal berhasil dibuat', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal membuat kategori soal: ' . $e->getMessage());
            }
        });
    }

    /**
     * Update question category
     *
     * @param string $id
     * @param array $data Validated data from QuestionCategoryRequest
     * @return array
     */
    public function updateCategory(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            try {
                // Check if category exists
                $category = $this->questionCategoryRepository->find($id);
                if (!$category) {
                    return $this->errorResponse('Kategori soal tidak ditemukan', null, 404);
                }

                // Update category
                $updated = $this->questionCategoryRepository->update($id, $data);

                if (!$updated) {
                    return $this->errorResponse('Gagal update kategori soal');
                }

                // Get updated category
                $updatedCategory = $this->questionCategoryRepository->find($id);

                return $this->successResponse($updatedCategory, 'Kategori soal berhasil diupdate');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal update kategori soal: ' . $e->getMessage());
            }
        });
    }

    /**
     * Delete question category
     *
     * @param string $id
     * @return array
     */
    public function deleteCategory(string $id): array
    {
        return DB::transaction(function () use ($id) {
            try {
                // Check if category exists
                $category = $this->questionCategoryRepository->find($id);
                if (!$category) {
                    return $this->errorResponse('Kategori soal tidak ditemukan', null, 404);
                }

                // Check if category has questions
                if ($category->questions()->exists()) {
                    return $this->errorResponse('Kategori soal tidak dapat dihapus karena masih memiliki soal');
                }

                // Delete category
                $deleted = $this->questionCategoryRepository->delete($id);

                if (!$deleted) {
                    return $this->errorResponse('Gagal menghapus kategori soal');
                }

                return $this->successResponse(null, 'Kategori soal berhasil dihapus');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menghapus kategori soal: ' . $e->getMessage());
            }
        });
    }
}
