<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\UserExam;
use App\Models\QuestionExam;
use App\Jobs\ProcessExamRolling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExamRollingBackgroundService Service Class
 *
 * Service ini menangani rolling student yang mengikuti ujian menggunakan background job
 * Membuat user_exam dan user_answer untuk setiap student dengan soal yang random
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class ExamRollingBackgroundService extends BaseService
{
    /**
     * Roll student to different group and process exam via background job
     *
     * @param string $userExamId
     * @param string $newGroupId
     * @return array
     */
    public function rollStudentToGroupWithBackgroundJob(string $userExamId, string $newGroupId): array
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

                // Get exam
                $exam = $userExam->exam;

                // Delete existing user_answers and user_exam
                UserExam::where('id', $userExamId)->delete();

                // Dispatch background job to create new user_exam and user_answers
                ProcessExamRolling::dispatch($exam, $student);

                // Log the rolling action
                $this->logRollingAction($userExam, $oldGroup, $newGroup);

                return $this->successResponse([
                    'student' => $student->fresh(['group']),
                    'old_group' => $oldGroup,
                    'new_group' => $newGroup,
                    'user_exam_id' => $userExamId,
                    'exam_id' => $exam->id,
                    'background_job_dispatched' => true,
                ], 'Siswa berhasil dipindahkan ke grup baru. Proses pembuatan ujian akan dilakukan di background.');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal memindahkan siswa: ' . $e->getMessage());
            }
        });
    }

    /**
     * Roll multiple students to different groups with background jobs
     *
     * @param array $rollData Array of ['user_exam_id' => int, 'new_group_id' => int]
     * @return array
     */
    public function rollMultipleStudentsWithBackgroundJobs(array $rollData): array
    {
        return DB::transaction(function () use ($rollData) {
            try {
                $results = [];
                $errors = [];

                foreach ($rollData as $index => $data) {
                    try {
                        $userExamId = $data['user_exam_id'];
                        $newGroupId = $data['new_group_id'];

                        $result = $this->rollStudentToGroupWithBackgroundJob($userExamId, $newGroupId);

                        if ($result['success']) {
                            $results[] = [
                                'user_exam_id' => $userExamId,
                                'new_group_id' => $newGroupId,
                                'status' => 'success',
                                'message' => 'Background job dispatched',
                            ];
                        } else {
                            $errors[] = [
                                'user_exam_id' => $userExamId,
                                'new_group_id' => $newGroupId,
                                'error' => $result['message'],
                            ];
                        }

                    } catch (\Exception $e) {
                        $errors[] = [
                            'user_exam_id' => $data['user_exam_id'] ?? 'unknown',
                            'new_group_id' => $data['new_group_id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                return $this->successResponse([
                    'successful_rolls' => $results,
                    'errors' => $errors,
                    'total_success' => count($results),
                    'total_errors' => count($errors),
                    'background_jobs_dispatched' => count($results),
                ], 'Proses rolling selesai. Background jobs telah di-dispatch.');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse('Gagal memproses rolling: ' . $e->getMessage());
            }
        });
    }

    /**
     * Process exam for all students in a group
     *
     * @param string $examId
     * @param string $groupId
     * @return array
     */
    public function processExamForGroup(string $examId, string $groupId): array
    {
        try {
            $exam = Exam::find($examId);
            if (!$exam) {
                return $this->errorResponse('Ujian tidak ditemukan', null, 404);
            }

            $group = StudentGroup::find($groupId);
            if (!$group) {
                return $this->errorResponse('Grup tidak ditemukan', null, 404);
            }

            // Get all active students in the group
            $students = Student::where('group_id', $groupId)
                ->where('status', Student::STATUS_ACTIVE)
                ->get();

            if ($students->isEmpty()) {
                return $this->errorResponse('Tidak ada siswa aktif dalam grup ini');
            }

            $dispatchedJobs = 0;
            $errors = [];

            foreach ($students as $student) {
                try {
                    // Check if student already has user_exam for this exam
                    $existingUserExam = UserExam::where('exam_id', $examId)
                        ->where('student_id', $student->id)
                        ->first();

                    if ($existingUserExam) {
                        Log::info('Student already has user_exam, skipping', [
                            'exam_id' => $examId,
                            'student_id' => $student->id,
                        ]);
                        continue;
                    }

                    // Dispatch background job
                    ProcessExamRolling::dispatch($exam, $student);
                    $dispatchedJobs++;

                    Log::info('Background job dispatched for student', [
                        'exam_id' => $examId,
                        'student_id' => $student->id,
                        'group_id' => $groupId,
                    ]);

                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Error dispatching job for student', [
                        'exam_id' => $examId,
                        'student_id' => $student->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $this->successResponse([
                'exam_id' => $examId,
                'group_id' => $groupId,
                'total_students' => $students->count(),
                'dispatched_jobs' => $dispatchedJobs,
                'errors' => $errors,
                'total_errors' => count($errors),
            ], 'Background jobs dispatched untuk pemrosesan ujian');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memproses ujian untuk grup: ' . $e->getMessage());
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
    private function logRollingAction($userExam, ?StudentGroup $oldGroup, StudentGroup $newGroup): void
    {
        Log::info('Student rolled during exam with background job', [
            'user_exam_id' => $userExam->id,
            'student_id' => $userExam->student_id,
            'exam_id' => $userExam->exam_id,
            'old_group_id' => $oldGroup ? $oldGroup->id : null,
            'new_group_id' => $newGroup->id,
            'old_group_name' => $oldGroup ? $oldGroup->name : null,
            'new_group_name' => $newGroup->name,
            'background_job_dispatched' => true,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
