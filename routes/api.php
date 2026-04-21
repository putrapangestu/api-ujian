<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamMonitoringController;
use App\Http\Controllers\Api\ExamRollingController;
use App\Http\Controllers\Api\QuestionBankController;
use App\Http\Controllers\Api\QuestionCategoryController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionGroupController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentGroupController;
use App\Http\Controllers\Api\UserController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * API Routes
 *
 * File ini berisi semua routes untuk API endpoints
 *
 * @author Development Team
 *
 * @version 1.0.0
 */

// Public routes
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

// Internal routes for Socket.IO synchronization
Route::prefix('internal')->middleware('internal.api.key')->group(function () {
    Route::post('/sync-answers', [\App\Http\Controllers\Api\Internal\ExamSyncController::class, 'syncAnswers']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'profile'])->name('auth.profile');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('auth.update-profile');
    });

    // Exam routes
    Route::prefix('exams')->group(function () {
        Route::get('/', [ExamController::class, 'index'])->name('exams.index');
        Route::get('/history', [ExamController::class, 'history'])->name('exams.history');
        Route::get('/history/{userExamId}', [ExamController::class, 'historyDetail'])->name('exams.history-detail');
        Route::get('/{id}/questions-for-student/{userId}', [ExamController::class, 'getExamQuestionsForStudent'])->name('exams.questions-for-student');
        Route::get('/today', [ExamController::class, 'today'])->name('exams.today');
        Route::get('/{id}', [ExamController::class, 'show'])->name('exams.show');
        Route::get('/{id}/questions', [ExamController::class, 'getExamQuestions'])->name('exams.questions');
        Route::get('/{id}/user-exams', [ExamController::class, 'getUserExamsByExam'])->name('exams.user-exams');
        Route::get('/{id}/user-exams/export', [ExamController::class, 'exportUserExams'])->name('exams.user-exams.export');
        Route::post('/{id}/start', [ExamController::class, 'startExam'])->name('exams.start');
        Route::post('/{id}/finish', [ExamController::class, 'finishExam'])->name('exams.finish');
        Route::post('/{exam}/select-questions', [ExamController::class, 'selectQuestionsFromBanks'])->name('exams.select-questions');
        Route::post('/validate-question-banks', [ExamController::class, 'validateQuestionBankSelection'])->name('exams.validate-question-banks');
    });

    // Student Group routes
    Route::prefix('student-groups')->group(function () {
        Route::get('/', [StudentGroupController::class, 'index'])->name('student-groups.index');
        Route::get('/all', [StudentGroupController::class, 'listAll'])->name('student-groups.all');
        Route::get('/{id}', [StudentGroupController::class, 'show'])->name('student-groups.show');
        Route::post('/roll-students', [StudentGroupController::class, 'rollStudents'])->name('student-groups.roll');
        Route::post('/roll-multiple', [StudentGroupController::class, 'rollMultipleStudents'])->name('student-groups.roll-multiple');
    });

    // Student routes
    Route::prefix('students')->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('students.index');
        Route::get('/all', [StudentController::class, 'list'])->name('students.all');
        Route::get('/download-template', [StudentController::class, 'downloadTemplate'])->name('students.download-template');
        Route::get('/{id}', [StudentController::class, 'show'])->name('students.show');
        Route::get('/{id}/exam-history', [StudentController::class, 'getExamHistory'])->name('students.exam-history');
        Route::get('/{id}/exam-history/export', [StudentController::class, 'exportExamHistory'])->name('students.exam-history-export');
        Route::post('/import', [StudentController::class, 'import'])->name('students.import');
    });

    // Question Category routes
    Route::prefix('question-categories')->group(function () {
        Route::get('/', [QuestionCategoryController::class, 'index'])->name('question-categories.index');
        Route::get('/{id}', [QuestionCategoryController::class, 'show'])->name('question-categories.show');
    });

    // Question Group routes
    Route::prefix('question-groups')->group(function () {
        Route::get('/', [QuestionGroupController::class, 'index'])->name('question-groups.index');
        Route::get('/all', [QuestionGroupController::class, 'listAll'])->name('question-groups.all');
        Route::get('/{id}', [QuestionGroupController::class, 'show'])->name('question-groups.show');
        Route::post('/import', [QuestionGroupController::class, 'import'])->name('question-groups.import');
        Route::get('/export', [QuestionGroupController::class, 'export'])->name('question-groups.export');
    });

    // Questions
    Route::post('questions/import', [QuestionController::class, 'import'])->name('questions.import');
    Route::apiResource('questions', QuestionController::class);

    // Question Bank routes
    Route::prefix('question-bank')->group(function () {
        Route::post('/import', [QuestionBankController::class, 'import'])->name('question-bank.import');
        Route::post('/import-moodle', [QuestionBankController::class, 'importMoodleXml'])->name('question-bank.import-moodle');
        Route::post('/import-examview', [QuestionBankController::class, 'importExamView'])->name('question-bank.import-examview');
        Route::get('/export', [QuestionBankController::class, 'export'])->name('question-bank.export');
        Route::get('/download-template', [QuestionBankController::class, 'downloadTemplate'])->name('question-bank.download-template');
    });

    // Exam Monitoring routes
    Route::prefix('exam-monitoring')->group(function () {
        Route::get('/active', [ExamMonitoringController::class, 'getActiveExams'])->name('exam-monitoring.active');
        Route::post('/record-action', [ExamMonitoringController::class, 'recordAction'])->name('exam-monitoring.record');
        Route::get('/realtime/{examId}', [ExamMonitoringController::class, 'getRealtimeMonitoring'])->name('exam-monitoring.realtime');
        Route::get('/student/{studentId}/{examId}', [ExamMonitoringController::class, 'getStudentMonitoring'])->name('exam-monitoring.student');
        Route::get('/summary/{examId}', [ExamMonitoringController::class, 'getExamSummary'])->name('exam-monitoring.summary');
        Route::get('/by-group/{examId}/{questionGroupId}', [ExamMonitoringController::class, 'getMonitoringByQuestionGroup'])->name('exam-monitoring.by-group');
        Route::get('/by-category/{examId}', [ExamMonitoringController::class, 'getMonitoringByCategory'])->name('exam-monitoring.by-category');
        Route::post('/force-submit', [ExamMonitoringController::class, 'forceSubmit'])->name('exam-monitoring.force-submit');
        Route::post('/unblock', [ExamMonitoringController::class, 'unblockStudent'])->name('exam-monitoring.unblock');
    });

    // Exam Rolling routes
    Route::prefix('exam-rolling')->group(function () {
        Route::post('/roll-student', [ExamRollingController::class, 'rollStudent'])->name('exam-rolling.roll-student');
        Route::post('/roll-multiple', [ExamRollingController::class, 'rollMultipleStudents'])->name('exam-rolling.roll-multiple');
        Route::get('/rollable-students/{examId}', [ExamRollingController::class, 'getRollableStudents'])->name('exam-rolling.rollable-students');
        Route::post('/validate', [ExamRollingController::class, 'validateRolling'])->name('exam-rolling.validate');
        Route::post('/roll-student-background', [ExamRollingController::class, 'rollStudentWithBackgroundJob'])->name('exam-rolling.roll-student-background');
        Route::post('/roll-multiple-background', [ExamRollingController::class, 'rollMultipleStudentsWithBackgroundJobs'])->name('exam-rolling.roll-multiple-background');
        Route::post('/process-group/{examId}', [ExamRollingController::class, 'processExamForGroup'])->name('exam-rolling.process-group');
    });

    // Exam Question Selection routes
    Route::prefix('exam-questions')->group(function () {
        Route::post('/{exam}/select-from-banks', [ExamController::class, 'selectQuestionsFromBanks'])->name('exam-questions.select-from-banks');
        Route::post('/validate-banks', [ExamController::class, 'validateQuestionBankSelection'])->name('exam-questions.validate-banks');
        // Route::post('/{exam}/for-student', [ExamController::class, 'getExamQuestionsForStudent'])->name('exam-questions.for-student');
    });

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/summary', [DashboardController::class, 'getSummary'])->name('admin.dashboard.summary');
            Route::get('/active-exams', [DashboardController::class, 'getActiveExams'])->name('admin.dashboard.active-exams');
            Route::get('/charts/students', [DashboardController::class, 'getStudentChart'])->name('admin.dashboard.charts.students');
            Route::get('/charts/exams', [DashboardController::class, 'getExamChart'])->name('admin.dashboard.charts.exams');
        });

        // Exam management
        Route::post('/exams', [ExamController::class, 'store'])->name('admin.exams.store');
        Route::put('/exams/{id}', [ExamController::class, 'update'])->name('admin.exams.update');
        Route::delete('/exams/{id}', [ExamController::class, 'destroy'])->name('admin.exams.destroy');

        // Student Group management
        Route::post('/student-groups', [StudentGroupController::class, 'store'])->name('admin.student-groups.store');
        Route::put('/student-groups/{id}', [StudentGroupController::class, 'update'])->name('admin.student-groups.update');
        Route::delete('/student-groups/{id}', [StudentGroupController::class, 'destroy'])->name('admin.student-groups.destroy');

        // Student management
        Route::get('/students/export', [StudentController::class, 'export'])->name('admin.students.export');
        Route::post('/students', [StudentController::class, 'store'])->name('admin.students.store');
        Route::put('/students/{id}', [StudentController::class, 'update'])->name('admin.students.update');
        Route::delete('/students/{id}', [StudentController::class, 'destroy'])->name('admin.students.destroy');

        // Question Category management
        Route::post('/question-categories', [QuestionCategoryController::class, 'store'])->name('admin.question-categories.store');
        Route::put('/question-categories/{id}', [QuestionCategoryController::class, 'update'])->name('admin.question-categories.update');
        Route::delete('/question-categories/{id}', [QuestionCategoryController::class, 'destroy'])->name('admin.question-categories.destroy');

        // Question Group management
        Route::post('/question-groups', [QuestionGroupController::class, 'store'])->name('admin.question-groups.store');
        Route::put('/question-groups/{id}', [QuestionGroupController::class, 'update'])->name('admin.question-groups.update');
        Route::delete('/question-groups/{id}', [QuestionGroupController::class, 'destroy'])->name('admin.question-groups.destroy');

        // User management
        Route::get('/users/all', [UserController::class, 'listAll'])->name('admin.users.all');
    });
});

// Test route
Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working!',
        'timestamp' => Carbon::now()->toDateTimeString(),
    ]);
});

// Include Socket.IO and testing routes
require __DIR__.'/socket-io.php';
