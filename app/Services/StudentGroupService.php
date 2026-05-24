<?php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Repositories\StudentGroupRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * StudentGroup Service Class
 *
 * Service ini menangani semua operasi terkait grup siswa
 * Menggunakan database transaction untuk operasi yang melibatkan multiple tables
 *
 * @author Development Team
 *
 * @version 1.0.0
 */
class StudentGroupService extends BaseService
{
    protected StudentGroupRepositoryInterface $studentGroupRepository;

    /**
     * StudentGroupService constructor
     */
    public function __construct(StudentGroupRepositoryInterface $studentGroupRepository)
    {
        $this->studentGroupRepository = $studentGroupRepository;
    }

    /**
     * Get all student groups (paginated)
     */
    public function getAllGroups(array $filters = [], int $perPage = 15): array
    {
        try {
            $query = StudentGroup::query()
                ->withCount(['students as active_students_count' => function ($q) {
                    $q->whereNotNull('user_id')
                      ->whereHas('user', function ($q2) {
                          $q2->whereNull('deleted_at');
                      });
                }]);

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
            return $this->errorResponse('Gagal mengambil daftar grup siswa: '.$e->getMessage());
        }
    }

    /**
     * Get all student groups without pagination
     */
    public function getAllGroupsList(array $filters = []): array
    {
        try {
            $query = StudentGroup::query()
                ->withCount(['students as active_students_count' => function ($q) {
                    $q->whereNotNull('user_id')
                      ->whereHas('user', function ($q2) {
                          $q2->whereNull('deleted_at');
                      });
                }]);

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
            return $this->errorResponse('Gagal mengambil daftar grup siswa: '.$e->getMessage());
        }
    }

    /**
     * Get student group by ID
     */
    public function getGroupById(string $id): array
    {
        try {
            $group = $this->studentGroupRepository->find($id, ['students']);

            if (! $group) {
                return $this->errorResponse('Grup siswa tidak ditemukan', null, 404);
            }

            return $this->successResponse($group, 'Detail grup siswa berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil detail grup siswa: '.$e->getMessage());
        }
    }

    /**
     * Create new student group
     *
     * @param  array  $data  Validated data from StudentGroupRequest
     */
    public function createGroup(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $group = $this->studentGroupRepository->create($data);

            return $this->successResponse($group, 'Grup siswa berhasil dibuat', 201);
        });
    }

    /**
     * Update student group
     *
     * @param  array  $data  Validated data from StudentGroupRequest
     */
    public function updateGroup(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            // Check if group exists
            $group = $this->studentGroupRepository->find($id);
            if (! $group) {
                return $this->errorResponse('Grup siswa tidak ditemukan', null, 404);
            }

            // Update group
            $updated = $this->studentGroupRepository->update($id, $data);

            if (! $updated) {
                return $this->errorResponse('Gagal update grup siswa');
            }

            // Get updated group
            $updatedGroup = $this->studentGroupRepository->find($id);

            return $this->successResponse($updatedGroup, 'Grup siswa berhasil diupdate');
        });
    }

    /**
     * Delete student group
     */
    public function deleteGroup(string $id): array
    {
        return DB::transaction(function () use ($id) {
            // Check if group exists
            $group = $this->studentGroupRepository->find($id);
            if (! $group) {
                return $this->errorResponse('Grup siswa tidak ditemukan', null, 404);
            }

            // Check if group has students
            if ($group->students()->exists()) {
                return $this->errorResponse('Grup siswa tidak dapat dihapus karena masih memiliki siswa');
            }

            // Delete group
            $deleted = $this->studentGroupRepository->delete($id);

            if (! $deleted) {
                return $this->errorResponse('Gagal menghapus grup siswa');
            }

            return $this->successResponse(null, 'Grup siswa berhasil dihapus');
        });
    }

    /**
     * Roll students to new group
     */
    public function rollStudents(string $fromGroupId, string $toGroupId, array $studentIds): array
    {
        return DB::transaction(function () use ($fromGroupId, $toGroupId, $studentIds) {
            // Validate groups
            $fromGroup = $this->studentGroupRepository->find($fromGroupId);
            $toGroup = $this->studentGroupRepository->find($toGroupId);

            if (! $fromGroup || ! $toGroup) {
                return $this->errorResponse('Grup asal atau tujuan tidak ditemukan', null, 404);
            }

            // Check if target group has available slots
            $availableSlots = $toGroup->getAvailableSlots();
            if ($availableSlots < count($studentIds)) {
                return $this->errorResponse('Grup tujuan tidak memiliki cukup slot tersedia');
            }

            // Update student groups
            $updatedCount = Student::whereIn('id', $studentIds)
                ->where('group_id', $fromGroupId)
                ->update(['group_id' => $toGroupId]);

            return $this->successResponse([
                'updated_count' => $updatedCount,
                'total_requested' => count($studentIds),
            ], 'Siswa berhasil dipindahkan ke grup baru');
        });
    }

    public function rollMultipleStudents(array $rolls): array
    {
        return DB::transaction(function () use ($rolls) {
            if (count($rolls) < 1) {
                return $this->errorResponse('Data rolling wajib diisi');
            }

            $normalizedRolls = [];
            $studentIds = [];
            $destinationGroupIds = [];
            $seenStudentIds = [];
            $errors = [];

            foreach ($rolls as $index => $roll) {
                $studentId = $roll['student_id'] ?? null;
                $newGroupId = $roll['new_group_id'] ?? null;

                if (! $studentId || ! $newGroupId) {
                    $errors[] = 'Item '.($index + 1).': student_id dan new_group_id wajib diisi';

                    continue;
                }

                if (isset($seenStudentIds[$studentId])) {
                    $errors[] = 'Item '.($index + 1).': student_id duplikat';

                    continue;
                }

                $seenStudentIds[$studentId] = true;
                $studentIds[] = $studentId;
                $destinationGroupIds[] = $newGroupId;
                $normalizedRolls[] = [
                    'index' => $index,
                    'student_id' => $studentId,
                    'new_group_id' => $newGroupId,
                ];
            }

            if (count($normalizedRolls) < 1) {
                return $this->successResponse([
                    'successful_rolls' => [],
                    'errors' => $errors,
                    'total_success' => 0,
                    'total_errors' => count($errors),
                ], 'Proses rolling selesai');
            }

            $students = Student::query()
                ->whereIn('id', $studentIds)
                ->lockForUpdate()
                ->get(['id', 'group_id']);

            $studentsById = $students->keyBy('id');
            $sourceGroupIds = $students
                ->pluck('group_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $allGroupIds = array_values(array_unique(array_merge($destinationGroupIds, $sourceGroupIds)));

            $groups = StudentGroup::query()
                ->whereIn('id', $allGroupIds)
                ->lockForUpdate()
                ->get(['id', 'max_students']);

            $groupsById = $groups->keyBy('id');
            $remainingSlots = [];

            foreach ($groups as $group) {
                $currentCount = Student::query()
                    ->where('group_id', $group->id)
                    ->whereNotNull('user_id')
                    ->whereHas('user', function ($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->lockForUpdate()
                    ->count();

                $remainingSlots[$group->id] = $group->max_students - $currentCount;
            }

            $successfulRolls = [];
            $studentIdsByGroup = [];

            foreach ($normalizedRolls as $roll) {
                $itemNumber = $roll['index'] + 1;
                $student = $studentsById->get($roll['student_id']);
                $newGroupId = $roll['new_group_id'];

                if (! $student) {
                    $errors[] = 'Item '.$itemNumber.': siswa tidak ditemukan';

                    continue;
                }

                $newGroup = $groupsById->get($newGroupId);
                if (! $newGroup) {
                    $errors[] = 'Item '.$itemNumber.': grup tujuan tidak ditemukan';

                    continue;
                }

                $oldGroupId = $student->group_id;
                if ($oldGroupId === $newGroupId) {
                    $successfulRolls[] = [
                        'student_id' => $student->id,
                        'old_group_id' => $oldGroupId,
                        'new_group_id' => $newGroupId,
                        'status' => 'skipped',
                    ];

                    continue;
                }

                if (($remainingSlots[$newGroupId] ?? 0) <= 0) {
                    $errors[] = 'Item '.$itemNumber.': grup tujuan sudah penuh';

                    continue;
                }

                $studentIdsByGroup[$newGroupId] = $studentIdsByGroup[$newGroupId] ?? [];
                $studentIdsByGroup[$newGroupId][] = $student->id;

                $remainingSlots[$newGroupId] = $remainingSlots[$newGroupId] - 1;
                if ($oldGroupId && array_key_exists($oldGroupId, $remainingSlots)) {
                    $remainingSlots[$oldGroupId] = $remainingSlots[$oldGroupId] + 1;
                }

                $student->group_id = $newGroupId;

                $successfulRolls[] = [
                    'student_id' => $student->id,
                    'old_group_id' => $oldGroupId,
                    'new_group_id' => $newGroupId,
                    'status' => 'moved',
                ];
            }

            foreach ($studentIdsByGroup as $groupId => $ids) {
                Student::query()
                    ->whereIn('id', $ids)
                    ->update(['group_id' => $groupId]);
            }

            return $this->successResponse([
                'successful_rolls' => $successfulRolls,
                'errors' => $errors,
                'total_success' => count($successfulRolls),
                'total_errors' => count($errors),
            ], 'Proses rolling selesai');
        });
    }
}
