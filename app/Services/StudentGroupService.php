<?php

namespace App\Services;

use App\Models\StudentGroup;
use App\Repositories\StudentGroupRepositoryInterface;
use App\Helpers\ResponseHelper;
use Illuminate\Support\Facades\DB;

/**
 * StudentGroup Service Class
 *
 * Service ini menangani semua operasi terkait grup siswa
 * Menggunakan database transaction untuk operasi yang melibatkan multiple tables
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class StudentGroupService extends BaseService
{
    /**
     * @var StudentGroupRepositoryInterface
     */
    protected StudentGroupRepositoryInterface $studentGroupRepository;

    /**
     * StudentGroupService constructor
     *
     * @param StudentGroupRepositoryInterface $studentGroupRepository
     */
    public function __construct(StudentGroupRepositoryInterface $studentGroupRepository)
    {
        $this->studentGroupRepository = $studentGroupRepository;
    }

    /**
     * Get all student groups (paginated)
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllGroups(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = StudentGroup::query()->withCount('students');

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

            return ResponseHelper::paginate($groups, 'Daftar grup siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar grup siswa: ' . $e->getMessage());
        }
    }

    /**
     * Get all student groups without pagination
     *
     * @param array $filters
     * @return array
     */
    public function getAllGroupsList(array $filters = []): array
    {
        try {
            $query = StudentGroup::query()->withCount('students');

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

            return $this->successResponse($groups, 'Daftar grup siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar grup siswa: ' . $e->getMessage());
        }
    }

    /**
     * Get student group by ID
     *
     * @param string $id
     * @return array
     */
    public function getGroupById(string $id): array
    {
        try {
            $group = $this->studentGroupRepository->find($id, ['students']);

            if (!$group) {
                return $this->errorResponse('Grup siswa tidak ditemukan', null, 404);
            }

            return $this->successResponse($group, 'Detail grup siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil detail grup siswa: ' . $e->getMessage());
        }
    }

    /**
     * Create new student group
     *
     * @param array $data Validated data from StudentGroupRequest
     * @return array
     */
    public function createGroup(array $data): array
    {
        return DB::transaction(function () use ($data) {
            try {
                $group = $this->studentGroupRepository->create($data);

                return $this->successResponse($group, 'Grup siswa berhasil dibuat', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal membuat grup siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Update student group
     *
     * @param string $id
     * @param array $data Validated data from StudentGroupRequest
     * @return array
     */
    public function updateGroup(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            try {
                // Check if group exists
                $group = $this->studentGroupRepository->find($id);
                if (!$group) {
                    return $this->errorResponse('Grup siswa tidak ditemukan', null, 404);
                }

                // Update group
                $updated = $this->studentGroupRepository->update($id, $data);

                if (!$updated) {
                    return $this->errorResponse('Gagal update grup siswa');
                }

                // Get updated group
                $updatedGroup = $this->studentGroupRepository->find($id);

                return $this->successResponse($updatedGroup, 'Grup siswa berhasil diupdate');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal update grup siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Delete student group
     *
     * @param string $id
     * @return array
     */
    public function deleteGroup(string $id): array
    {
        return DB::transaction(function () use ($id) {
            try {
                // Check if group exists
                $group = $this->studentGroupRepository->find($id);
                if (!$group) {
                    return $this->errorResponse('Grup siswa tidak ditemukan', null, 404);
                }

                // Check if group has students
                if ($group->students()->exists()) {
                    return $this->errorResponse('Grup siswa tidak dapat dihapus karena masih memiliki siswa');
                }

                // Delete group
                $deleted = $this->studentGroupRepository->delete($id);

                if (!$deleted) {
                    return $this->errorResponse('Gagal menghapus grup siswa');
                }

                return $this->successResponse(null, 'Grup siswa berhasil dihapus');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal menghapus grup siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Roll students to new group
     *
     * @param string $fromGroupId
     * @param string $toGroupId
     * @param array $studentIds
     * @return array
     */
    public function rollStudents(string $fromGroupId, string $toGroupId, array $studentIds): array
    {
        return DB::transaction(function () use ($fromGroupId, $toGroupId, $studentIds) {
            try {
                // Validate groups
                $fromGroup = $this->studentGroupRepository->find($fromGroupId);
                $toGroup = $this->studentGroupRepository->find($toGroupId);

                if (!$fromGroup || !$toGroup) {
                    return $this->errorResponse('Grup asal atau tujuan tidak ditemukan', null, 404);
                }

                // Check if target group has available slots
                $availableSlots = $toGroup->getAvailableSlots();
                if ($availableSlots < count($studentIds)) {
                    return $this->errorResponse('Grup tujuan tidak memiliki cukup slot tersedia');
                }

                // Update student groups
                $updatedCount = \App\Models\Student::whereIn('id', $studentIds)
                    ->where('group_id', $fromGroupId)
                    ->update(['group_id' => $toGroupId]);

                return $this->successResponse([
                    'updated_count' => $updatedCount,
                    'total_requested' => count($studentIds)
                ], 'Siswa berhasil dipindahkan ke grup baru');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal memindahkan siswa: ' . $e->getMessage());
            }
        });
    }
}
