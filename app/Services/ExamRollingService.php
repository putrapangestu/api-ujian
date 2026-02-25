<?php

namespace App\Services;

use App\Models\UserExam;
use App\Models\Student;
use App\Models\Exam;
use App\Models\StudentGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExamRolling Service Class
 *
 * Service ini menangani rolling user yang mengikuti ujian
 * Memungkinkan pemindahan siswa antar grup yang sedang mengikuti ujian
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class ExamRollingService extends BaseService
{
    /**
     * Roll student to different group during exam
     *
     * @param string $userExamId
     * @param string $newGroupId
     * @return array
     */
    public function rollStudentToGroup(string $userExamId, string $newGroupId): array
    {
        return DB::transaction(function () use ($userExamId, $newGroupId) {
            try {
                // Get user exam
                $userExam = UserExam::find($userExamId);
                if (!$userExam) {
                    return $this->errorResponse('Ujian siswa tidak ditemukan', null, 404);
                }

                // Check if exam is still in progress
                if ($userExam->status !== UserExam::STATUS_IN_PROGRESS) {
                    return $this->errorResponse('Ujian sudah selesai atau belum dimulai');
                }

                // Get student
                $student = $userExam->student;
                if (!$student) {
                    return $this->errorResponse('Data siswa tidak ditemukan', null, 404);
                }

                // Get new group
                $newGroup = StudentGroup::find($newGroupId);
                if (!$newGroup) {
                    return $this->errorResponse('Grup tujuan tidak ditemukan', null, 404);
                }

                // Check if new group has available slots
                if (!$newGroup->hasAvailableSlots()) {
                    return $this->errorResponse('Grup tujuan sudah penuh');
                }

                // Get old group
                $oldGroup = $student->group;

                // Update student group
                $student->update(['group_id' => $newGroupId]);

                // Log the rolling action
                $this->logRollingAction($userExam, $oldGroup, $newGroup);

                // Broadcast to Socket.IO (if configured)
                $this->broadcastRollingEvent($userExam, $oldGroup, $newGroup);

                return $this->successResponse([
                    'student' => $student->fresh(['group']),
                    'old_group' => $oldGroup,
                    'new_group' => $newGroup,
                    'user_exam' => $userExam->fresh(),
                ], 'Siswa berhasil dipindahkan ke grup baru');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal memindahkan siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Roll multiple students to different groups during exam
     *
     * @param array $rollData Array of ['user_exam_id' => int, 'new_group_id' => int]
     * @return array
     */
    public function rollMultipleStudents(array $rollData): array
    {
        return DB::transaction(function () use ($rollData) {
            try {
                $results = [];
                $errors = [];

                foreach ($rollData as $index => $data) {
                    try {
                        $userExamId = $data['user_exam_id'];
                        $newGroupId = $data['new_group_id'];

                        $result = $this->rollStudentToGroup($userExamId, $newGroupId);

                        if ($result['success']) {
                            $results[] = $result['data'];
                        } else {
                            $errors[] = "Item " . ($index + 1) . ": " . $result['message'];
                        }

                    } catch (\Exception $e) {
                        $errors[] = "Item " . ($index + 1) . ": " . $e->getMessage();
                    }
                }

                return $this->successResponse([
                    'successful_rolls' => $results,
                    'errors' => $errors,
                    'total_success' => count($results),
                    'total_errors' => count($errors),
                ], 'Proses rolling selesai');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal memproses rolling: ' . $e->getMessage());
            }
        });
    }

    /**
     * Get students that can be rolled during exam
     *
     * @param string $examId
     * @return array
     */
    public function getRollableStudents(string $examId): array
    {
        try {
            // Get all students currently taking the exam
            $userExams = UserExam::where('exam_id', $examId)
                ->where('status', UserExam::STATUS_IN_PROGRESS)
                ->with(['student', 'student.group'])
                ->get();

            $rollableStudents = [];

            foreach ($userExams as $userExam) {
                $student = $userExam->student;
                if (!$student) continue;

                $currentGroup = $student->group;
                if (!$currentGroup) continue;

                // Get available groups (excluding current group)
                $availableGroups = StudentGroup::where('id', '!=', $currentGroup->id)
                    ->where('status', StudentGroup::STATUS_ACTIVE)
                    ->whereRaw('total_questions < max_students')
                    ->get();

                $rollableStudents[] = [
                    'user_exam' => $userExam,
                    'student' => $student,
                    'current_group' => $currentGroup,
                    'available_groups' => $availableGroups,
                    'progress' => $this->calculateExamProgress($userExam),
                    'time_remaining' => $this->calculateTimeRemaining($userExam),
                ];
            }

            return $this->successResponse($rollableStudents, 'Daftar siswa yang dapat dipindahkan');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data siswa: ' . $e->getMessage());
        }
    }

    /**
     * Validate rolling operation
     *
     * @param string $userExamId
     * @param string $newGroupId
     * @return array
     */
    public function validateRolling(string $userExamId, string $newGroupId): array
    {
        try {
            $userExam = UserExam::find($userExamId);
            if (!$userExam) {
                return $this->errorResponse('Ujian siswa tidak ditemukan', null, 404);
            }

            $student = $userExam->student;
            if (!$student) {
                return $this->errorResponse('Data siswa tidak ditemukan', null, 404);
            }

            $newGroup = StudentGroup::find($newGroupId);
            if (!$newGroup) {
                return $this->errorResponse('Grup tujuan tidak ditemukan', null, 404);
            }

            $validation = [
                'can_roll' => true,
                'reasons' => [],
            ];

            // Check exam status
            if ($userExam->status !== UserExam::STATUS_IN_PROGRESS) {
                $validation['can_roll'] = false;
                $validation['reasons'][] = 'Ujian sudah selesai atau belum dimulai';
            }

            // Check if new group has available slots
            if (!$newGroup->hasAvailableSlots()) {
                $validation['can_roll'] = false;
                $validation['reasons'][] = 'Grup tujuan sudah penuh';
            }

            // Check if student is already in the target group
            if ($student->group_id === $newGroupId) {
                $validation['can_roll'] = false;
                $validation['reasons'][] = 'Siswa sudah berada di grup tujuan';
            }

            return $this->successResponse($validation, 'Validasi rolling berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal melakukan validasi: ' . $e->getMessage());
        }
    }

    /**
     * Log rolling action for audit trail
     *
     * @param UserExam $userExam
     * @param StudentGroup|null $oldGroup
     * @param StudentGroup $newGroup
     * @return void
     */
    private function logRollingAction(UserExam $userExam, ?StudentGroup $oldGroup, StudentGroup $newGroup): void
    {
        Log::info('Student rolled during exam', [
            'user_exam_id' => $userExam->id,
            'student_id' => $userExam->student_id,
            'exam_id' => $userExam->exam_id,
            'old_group_id' => $oldGroup ? $oldGroup->id : null,
            'new_group_id' => $newGroup->id,
            'old_group_name' => $oldGroup ? $oldGroup->name : null,
            'new_group_name' => $newGroup->name,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Broadcast rolling event to Socket.IO
     *
     * @param UserExam $userExam
     * @param StudentGroup|null $oldGroup
     * @param StudentGroup $newGroup
     * @return void
     */
    private function broadcastRollingEvent(UserExam $userExam, ?StudentGroup $oldGroup, StudentGroup $newGroup): void
    {
        $broadcastData = [
            'type' => 'student_rolled_during_exam',
            'data' => [
                'user_exam_id' => $userExam->id,
                'student_id' => $userExam->student_id,
                'exam_id' => $userExam->exam_id,
                'old_group' => $oldGroup ? [
                    'id' => $oldGroup->id,
                    'name' => $oldGroup->name,
                ] : null,
                'new_group' => [
                    'id' => $newGroup->id,
                    'name' => $newGroup->name,
                ],
                'timestamp' => now()->toISOString(),
            ],
        ];

        // Log the broadcast (will be replaced with actual Socket.IO broadcast)
        Log::info('Socket.IO Broadcast - Student Rolled:', $broadcastData);
    }

    /**
     * Calculate exam progress percentage
     *
     * @param UserExam $userExam
     * @return float
     */
    private function calculateExamProgress(UserExam $userExam): float
    {
        $totalQuestions = $userExam->exam->total_questions;
        $answeredQuestions = $userExam->userAnswers()->count();

        if ($totalQuestions == 0) {
            return 0;
        }

        return round(($answeredQuestions / $totalQuestions) * 100, 2);
    }

    /**
     * Calculate time remaining for exam
     *
     * @param UserExam $userExam
     * @return int
     */
    private function calculateTimeRemaining(UserExam $userExam): int
    {
        if (!$userExam->start_time) {
            return 0;
        }

        $exam = $userExam->exam;
        $durationInSeconds = $exam->duration * 60;
        $elapsedTime = now()->diffInSeconds($userExam->start_time);
        $remainingTime = $durationInSeconds - $elapsedTime;

        return max(0, $remainingTime);
    }
}
