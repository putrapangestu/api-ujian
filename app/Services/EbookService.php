<?php

namespace App\Services;

use App\Repositories\EbookRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Log;

class EbookService extends BaseService
{
    protected EbookRepositoryInterface $repository;

    public function __construct(EbookRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllEbooks(Request $request): array
    {
        try {
            $perPage = $request->query('per_page', 10);
            $search = $request->query('search');
            $questionCategoryId = $request->query('question_category_id');

            $query = $this->repository->query()->with('category');

            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            if ($questionCategoryId) {
                $query->where('question_category_id', $questionCategoryId);
            }

            $ebooks = $query->latest()->paginate($perPage);

            return $this->successResponse([
                'items' => $ebooks->items(),
                'pagination' => [
                    'total' => $ebooks->total(),
                    'per_page' => $ebooks->perPage(),
                    'current_page' => $ebooks->currentPage(),
                    'last_page' => $ebooks->lastPage()
                ]
            ], 'Berhasil mengambil daftar ebook');
        } catch (Exception $e) {
            Log::error('Error getting ebooks: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil daftar ebook.', $e->getMessage(), 500);
        }
    }

    public function createEbook(Request $request): array
    {
        try {
            $data = $request->only(['name', 'question_category_id']);

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('ebooks', 'public');
                $data['file_path'] = $path;
            } else {
                return $this->errorResponse('File ebook wajib diunggah.', null, 422);
            }

            $ebook = $this->repository->create($data);

            return $this->successResponse($ebook, 'Ebook berhasil ditambahkan', 201);
        } catch (Exception $e) {
            Log::error('Error creating ebook: ' . $e->getMessage());
            return $this->errorResponse('Gagal menambahkan ebook.', $e->getMessage(), 500);
        }
    }

    public function getEbookById(string $id): array
    {
        try {
            $ebook = $this->repository->find($id);

            if (!$ebook) {
                return $this->errorResponse('Ebook tidak ditemukan.', null, 404);
            }

            return $this->successResponse($ebook, 'Berhasil mengambil detail ebook');
        } catch (Exception $e) {
            Log::error('Error getting ebook: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil detail ebook.', $e->getMessage(), 500);
        }
    }

    public function updateEbook(string $id, Request $request): array
    {
        try {
            $ebook = $this->repository->find($id);

            if (!$ebook) {
                return $this->errorResponse('Ebook tidak ditemukan.', null, 404);
            }

            $data = $request->only(['name', 'question_category_id']);

            if ($request->hasFile('file')) {
                // Delete old file if exists
                if ($ebook->file_path && Storage::disk('public')->exists($ebook->file_path)) {
                    Storage::disk('public')->delete($ebook->file_path);
                }

                $file = $request->file('file');
                $path = $file->store('ebooks', 'public');
                $data['file_path'] = $path;
            }

            $this->repository->update($id, $data);
            $updatedEbook = $this->repository->find($id);

            return $this->successResponse($updatedEbook, 'Ebook berhasil diperbarui');
        } catch (Exception $e) {
            Log::error('Error updating ebook: ' . $e->getMessage());
            return $this->errorResponse('Gagal memperbarui ebook.', $e->getMessage(), 500);
        }
    }

    public function deleteEbook(string $id): array
    {
        try {
            $ebook = $this->repository->find($id);

            if (!$ebook) {
                return $this->errorResponse('Ebook tidak ditemukan.', null, 404);
            }

            // Optional: delete physical file
            // if ($ebook->file_path && Storage::disk('public')->exists($ebook->file_path)) {
            //     Storage::disk('public')->delete($ebook->file_path);
            // }

            $this->repository->delete($id);

            return $this->successResponse(null, 'Ebook berhasil dihapus');
        } catch (Exception $e) {
            Log::error('Error deleting ebook: ' . $e->getMessage());
            return $this->errorResponse('Gagal menghapus ebook.', $e->getMessage(), 500);
        }
    }

    public function bulkDeleteEbooks(array $ids): array
    {
        try {
            // Optional: fetch to delete physical files
            // $ebooks = $this->repository->query()->whereIn('id', $ids)->get();
            // foreach ($ebooks as $ebook) {
            //     if ($ebook->file_path && Storage::disk('public')->exists($ebook->file_path)) {
            //         Storage::disk('public')->delete($ebook->file_path);
            //     }
            // }

            $this->repository->bulkDelete($ids);

            return $this->successResponse(null, 'Ebook berhasil dihapus');
        } catch (Exception $e) {
            Log::error('Error bulk deleting ebooks: ' . $e->getMessage());
            return $this->errorResponse('Gagal menghapus beberapa ebook.', $e->getMessage(), 500);
        }
    }

    public function getEbookFileForDownload(string $id): array
    {
        try {
            $ebook = $this->repository->find($id);

            if (!$ebook) {
                return $this->errorResponse('Ebook tidak ditemukan.', null, 404);
            }

            if (!$ebook->file_path || !Storage::disk('public')->exists($ebook->file_path)) {
                return $this->errorResponse('File ebook tidak ditemukan.', null, 404);
            }

            $fullPath = Storage::disk('public')->path($ebook->file_path);
            
            return [
                'success' => true,
                'path' => $fullPath,
                'name' => $ebook->name,
                'original_name' => basename($ebook->file_path),
                'file_path' => $ebook->file_path
            ];
        } catch (Exception $e) {
            Log::error('Error getting download file: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengunduh ebook.', $e->getMessage(), 500);
        }
    }
}