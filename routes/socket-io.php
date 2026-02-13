<?php

use Illuminate\Support\Facades\Route;

/**
 * Socket.IO Routes
 * 
 * Routes untuk Socket.IO integration dan testing
 * 
 * @package Routes
 * @author Development Team
 * @version 1.0.0
 */

// Socket.IO testing endpoints
Route::prefix('socket-io')->middleware('auth:sanctum')->group(function () {
    
    // Test Socket.IO connection
    Route::get('/test-connection', function () {
        return response()->json([
            'message' => 'Socket.IO connection test endpoint',
            'status' => 'ready',
            'timestamp' => now()->toISOString(),
        ]);
    })->name('socket-io.test-connection');

    // Test broadcast exam monitoring
    Route::post('/test-monitoring', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'user_exam_id' => 'required|integer',
            'student_id' => 'required|integer',
            'exam_id' => 'required|integer',
            'action' => 'required|string',
            'question_id' => 'nullable|integer',
            'answer' => 'nullable|string',
            'time_spent' => 'nullable|integer',
        ]);

        // Log the test broadcast
        \Illuminate\Support\Facades\Log::info('Socket.IO Test - Exam Monitoring', [
            'channel' => 'exam-monitoring',
            'event' => 'exam_action',
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json([
            'message' => 'Test broadcast sent',
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    })->name('socket-io.test-monitoring');

    // Test broadcast exam completion
    Route::post('/test-completion', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'user_exam_id' => 'required|integer',
            'student_id' => 'required|integer',
            'exam_id' => 'required|integer',
            'score' => 'required|integer',
            'passed' => 'required|boolean',
        ]);

        // Log the test broadcast
        \Illuminate\Support\Facades\Log::info('Socket.IO Test - Exam Completion', [
            'channel' => 'exam-completion',
            'event' => 'exam_completed',
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json([
            'message' => 'Test completion broadcast sent',
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    })->name('socket-io.test-completion');

    // Test broadcast group monitoring
    Route::post('/test-group-monitoring', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'exam_id' => 'required|integer',
            'question_group_id' => 'required|integer',
            'statistics' => 'required|array',
            'statistics.total_correct' => 'required|integer',
            'statistics.total_wrong' => 'required|integer',
            'statistics.average_score' => 'required|numeric',
        ]);

        // Log the test broadcast
        \Illuminate\Support\Facades\Log::info('Socket.IO Test - Group Monitoring', [
            'channel' => 'group-monitoring',
            'event' => 'group_statistics_updated',
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json([
            'message' => 'Test group monitoring broadcast sent',
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    })->name('socket-io.test-group-monitoring');

    // Test broadcast category monitoring
    Route::post('/test-category-monitoring', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'exam_id' => 'required|integer',
            'categories' => 'required|array',
        ]);

        // Log the test broadcast
        \Illuminate\Support\Facades\Log::info('Socket.IO Test - Category Monitoring', [
            'channel' => 'category-monitoring',
            'event' => 'category_statistics_updated',
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json([
            'message' => 'Test category monitoring broadcast sent',
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    })->name('socket-io.test-category-monitoring');

});

// Testing routes
Route::prefix('testing')->middleware('auth:sanctum')->group(function () {
    
    // Performance test endpoint
    Route::post('/performance', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'users' => 'required|integer|min:10|max:10000',
            'questions' => 'required|integer|min:1|max:200',
        ]);

        // Run performance test via command
        $output = [];
        $returnCode = 0;
        
        exec('php artisan test:performance --users=' . $request->users . ' --questions=' . $request->questions, $output, $returnCode);

        return response()->json([
            'message' => 'Performance test started',
            'parameters' => [
                'users' => $request->users,
                'questions' => $request->questions,
            ],
            'command_output' => $output,
            'return_code' => $returnCode,
            'timestamp' => now()->toISOString(),
        ]);
    })->name('testing.performance');

    // Security test endpoint
    Route::post('/security', function (\Illuminate\Http\Request $request) {
        // Run security test via command
        $output = [];
        $returnCode = 0;
        
        $verbose = $request->boolean('verbose') ? ' --verbose' : '';
        exec('php artisan test:security' . $verbose, $output, $returnCode);

        return response()->json([
            'message' => 'Security test started',
            'parameters' => [
                'verbose' => $request->boolean('verbose'),
            ],
            'command_output' => $output,
            'return_code' => $returnCode,
            'timestamp' => now()->toISOString(),
        ]);
    })->name('testing.security');

});