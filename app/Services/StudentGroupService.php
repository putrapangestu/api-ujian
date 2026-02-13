<?php

namespace App\Services;

use App\Models\StudentGroup;
use App\Repositories\StudentGroupRepositoryInterface;
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
     * Get all student groups
     * 
     * @param array $filters
     * @return array
     */
    public function getAllGroups(array $filters = []): array
    {
        try {
            $groups = $this->studentGroupRepository->getWithStudentCount();

            return $this->successResponse($groups, 'Daftar grup siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil daftar grup siswa: ' . $e->getMessage());
        }
    }

    /**
     * Get student group by ID
     * 
     * @param int $id
     * @return array
     */
    public function getGroupById(int $id): array
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
     * @param int $id
     * @param array $data Validated data from StudentGroupRequest
     * @return array
     */
    public function updateGroup(int $id, array $data): array
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
     * @param int $id
     * @return array
     */
    public function deleteGroup(int $id): array
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
     * @param int $fromGroupId
     * @param int $toGroupId
     * @param array $studentIds
     * @return array
     */
    public function rollStudents(int $fromGroupId, int $toGroupId, array $studentIds): array
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