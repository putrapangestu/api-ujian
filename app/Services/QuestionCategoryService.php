<?php

namespace App\Services;

use App\Models\QuestionCategory;
use App\Repositories\QuestionCategoryRepositoryInterface;
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
     * Get all question categories
     * 
     * @param array $filters
     * @return array
     */
    public function getAllCategories(array $filters = []): array
    {
        try {
            $categories = $this->questionCategoryRepository->getWithQuestionCount();

            return $this->successResponse($categories, 'Daftar kategori soal berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar kategori soal: ' . $e->getMessage());
        }
    }

    /**
     * Get question category by ID
     * 
     * @param int $id
     * @return array
     */
    public function getCategoryById(int $id): array
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
     * @param int $id
     * @param array $data Validated data from QuestionCategoryRequest
     * @return array
     */
    public function updateCategory(int $id, array $data): array
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
     * @param int $id
     * @return array
     */
    public function deleteCategory(int $id): array
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