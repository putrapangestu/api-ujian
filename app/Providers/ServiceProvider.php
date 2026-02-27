<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\QuestionBankService;
use App\Services\ExamRollingService;
use App\Services\ExamRollingBackgroundService;
use App\Services\ExamQuestionSelectorService;
use App\Services\SocketIOService;
use App\Repositories\UserExamRepositoryInterface;
use App\Repositories\UserExamRepository;
use App\Repositories\QuestionRepositoryInterface;
use App\Repositories\QuestionRepository;

/**
 * Service Provider untuk Services
 * 
 * Provider ini mengatur dependency injection untuk service classes
 * 
 * @package App\Providers
 * @author Development Team
 * @version 1.0.0
 */
class ServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind QuestionBank Service
        $this->app->singleton(QuestionBankService::class, function ($app) {
            return new QuestionBankService();
        });

        // Bind UserExam Repository
        $this->app->singleton(UserExamRepositoryInterface::class, function ($app) {
            return new UserExamRepository();
        });

        // Bind Question Repository
        $this->app->singleton(QuestionRepositoryInterface::class, function ($app) {
            return new QuestionRepository();
        });

        // Bind ExamRolling Service
        $this->app->singleton(ExamRollingService::class, function ($app) {
            return new ExamRollingService(
                $app->make(\App\Repositories\StudentRepositoryInterface::class)
            );
        });

        // Bind ExamRollingBackground Service
        $this->app->singleton(ExamRollingBackgroundService::class, function ($app) {
            return new ExamRollingBackgroundService();
        });

        // Bind ExamQuestionSelector Service
        $this->app->singleton(ExamQuestionSelectorService::class, function ($app) {
            return new ExamQuestionSelectorService();
        });

        // Bind SocketIO Service
        $this->app->singleton(SocketIOService::class, function ($app) {
            return new SocketIOService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
