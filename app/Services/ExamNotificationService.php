<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Exam;
use App\Models\UserExam;
use App\Models\StudentGroup;
use Illuminate\Support\Facades\Log;

/**
 * ExamNotification Service Class
 * 
 * Service ini menangani notifikasi untuk pengerjaan ujian
 * 
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class ExamNotificationService extends BaseService
{
    /**
     * Send exam start notification
     * 
     * @param UserExam $userExam
     * @return array
     */
    public function sendExamStartNotification(UserExam $userExam): array
    {
        try {
            $student = $userExam->student;
            $exam = $userExam->exam;

            $notificationData = [
                'type' => 'exam_started',
                'title' => 'Ujian Dimulai',
                'message' => "Ujian {$exam->title} telah dimulai. Durasi: {$exam->duration} menit.",
                'student_id' => $student->id,
                'exam_id' => $exam->id,
                'user_exam_id' => $userExam->id,
                'start_time' => $userExam->start_time->toISOString(),
                'end_time' => $userExam->start_time->copy()->addMinutes($exam->duration)->toISOString(),
            ];

            // Log notification (will be replaced with actual notification system)
            Log::info('Exam Start Notification', $notificationData);

            // Broadcast via Socket.IO (when configured)
            $this->broadcastNotification($notificationData);

            return $this->successResponse($notificationData, 'Notifikasi dimulai ujian berhasil dikirim');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengirim notifikasi dimulai ujian: ' . $e->getMessage());
        }
    }

    /**
     * Send exam reminder notification
     * 
     * @param UserExam $userExam
     * @param int $minutesRemaining
     * @return array
     */
    public function sendExamReminderNotification(UserExam $userExam, int $minutesRemaining): array
    {
        try {
            $student = $userExam->student;
            $exam = $userExam->exam;

            $notificationData = [
                'type' => 'exam_reminder',
                'title' => 'Pengingat Ujian',
                'message' => "Sisa waktu ujian {$exam->title}: {$minutesRemaining} menit lagi.",
                'student_id' => $student->id,
                'exam_id' => $exam->id,
                'user_exam_id' => $userExam->id,
                'minutes_remaining' => $minutesRemaining,
            ];

            // Log notification
            Log::info('Exam Reminder Notification', $notificationData);

            // Broadcast via Socket.IO
            $this->broadcastNotification($notificationData);

            return $this->successResponse($notificationData, 'Notifikasi pengingat ujian berhasil dikirim');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengirim notifikasi pengingat ujian: ' . $e->getMessage());
        }
    }

    /**
     * Send exam completion notification
     * 
     * @param UserExam $userExam
     * @return array
     */
    public function sendExamCompletionNotification(UserExam $userExam): array
    {
        try {
            $student = $userExam->student;
            $exam = $userExam->exam;
            $score = $userExam->score;
            $passed = $userExam->isPassed();

            $notificationData = [
                'type' => 'exam_completed',
                'title' => 'Ujian Selesai',
                'message' => "Ujian {$exam->title} telah selesai. Nilai: {$score}. " . 
                           ($passed ? 'Selamat, Anda lulus!' : 'Maaf, Anda belum lulus.'),
                'student_id' => $student->id,
                'exam_id' => $exam->id,
                'user_exam_id' => $userExam->id,
                'score' => $score,
                'passed' => $passed,
                'completed_at' => $userExam->end_time->toISOString(),
            ];

            // Log notification
            Log::info('Exam Completion Notification', $notificationData);

            // Broadcast via Socket.IO
            $this->broadcastNotification($notificationData);

            return $this->successResponse($notificationData, 'Notifikasi selesai ujian berhasil dikirim');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengirim notifikasi selesai ujian: ' . $e->getMessage());
        }
    }

    /**
     * Send exam assignment notification to group
     * 
     * @param StudentGroup $group
     * @param Exam $exam
     * @return array
     */
    public function sendExamAssignmentNotification(StudentGroup $group, Exam $exam): array
    {
        try {
            $students = $group->students()->where('status', Student::STATUS_ACTIVE)->get();
            $notificationsSent = [];

            foreach ($students as $student) {
                $notificationData = [
                    'type' => 'exam_assigned',
                    'title' => 'Ujian Baru Ditugaskan',
                    'message' => "Ujian {$exam->title} telah ditugaskan ke grup {$group->name}.",
                    'student_id' => $student->id,
                    'group_id' => $group->id,
                    'exam_id' => $exam->id,
                    'due_date' => $exam->due_date ?? null,
                ];

                // Log notification
                Log::info('Exam Assignment Notification', $notificationData);

                // Broadcast via Socket.IO
                $this->broadcastNotification($notificationData);

                $notificationsSent[] = $notificationData;
            }

            return $this->successResponse([
                'total_students' => $students->count(),
                'notifications_sent' => count($notificationsSent),
            ], 'Notifikasi penugasan ujian berhasil dikirim ke grup');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengirim notifikasi penugasan ujian: ' . $e->getMessage());
        }
    }

    /**
     * Send cheating alert notification
     * 
     * @param UserExam $userExam
     * @param string $alertType
     * @param string $details
     * @return array
     */
    public function sendCheatingAlertNotification(UserExam $userExam, string $alertType, string $details): array
    {
        try {
            $student = $userExam->student;
            $exam = $userExam->exam;

            $notificationData = [
                'type' => 'cheating_alert',
                'title' => 'Peringatan Kecurangan',
                'message' => "Siswa {$student->name} terdeteksi melakukan kecurangan pada ujian {$exam->title}.",
                'student_id' => $student->id,
                'exam_id' => $exam->id,
                'user_exam_id' => $userExam->id,
                'alert_type' => $alertType,
                'details' => $details,
                'alert_time' => now()->toISOString(),
            ];

            // Log notification
            Log::warning('Cheating Alert Notification', $notificationData);

            // Broadcast via Socket.IO
            $this->broadcastNotification($notificationData);

            return $this->successResponse($notificationData, 'Peringatan kecurangan berhasil dikirim');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengirim peringatan kecurangan: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast notification via Socket.IO
     * 
     * @param array $notificationData
     * @return void
     */
    private function broadcastNotification(array $notificationData): void
    {
        // This will be implemented when Socket.IO is configured
        // For now, we'll just log the broadcast data
        Log::info('Socket.IO Broadcast Notification', [
            'channel' => 'exam-notifications',
            'data' => $notificationData,
            'timestamp' => now()->toISOString(),
        ]);
    }
}