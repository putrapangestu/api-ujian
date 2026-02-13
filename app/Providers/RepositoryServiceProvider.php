<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\ExamRepositoryInterface;
use App\Repositories\ExamRepository;
use App\Repositories\StudentGroupRepositoryInterface;
use App\Repositories\StudentGroupRepository;
use App\Repositories\StudentRepositoryInterface;
use App\Repositories\StudentRepository;
use App\Repositories\QuestionCategoryRepositoryInterface;
use App\Repositories\QuestionCategoryRepository;
use App\Repositories\QuestionGroupRepositoryInterface;
use App\Repositories\QuestionGroupRepository;
use App\Repositories\UserExamRepositoryInterface;
use App\Repositories\UserExamRepository;
use App\Repositories\QuestionRepositoryInterface;
use App\Repositories\QuestionRepository;

/**
 * Repository Service Provider
 * 
 * Provider ini mengatur dependency injection untuk repository interfaces
 * 
 * @package App\Providers
 * @author Development Team
 * @version 1.0.0
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind User Repository
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        
        // Bind Exam Repository
        $this->app->bind(ExamRepositoryInterface::class, ExamRepository::class);
        
        // Bind StudentGroup Repository
        $this->app->bind(StudentGroupRepositoryInterface::class, StudentGroupRepository::class);
        
        // Bind Student Repository
        $this->app->bind(StudentRepositoryInterface::class, StudentRepository::class);
        
        // Bind QuestionCategory Repository
        $this->app->bind(QuestionCategoryRepositoryInterface::class, QuestionCategoryRepository::class);
        
        // Bind QuestionGroup Repository
        $this->app->bind(QuestionGroupRepositoryInterface::class, QuestionGroupRepository::class);
        
        // Bind UserExam Repository
        $this->app->bind(UserExamRepositoryInterface::class, UserExamRepository::class);
        
        // Bind Question Repository
        $this->app->bind(QuestionRepositoryInterface::class, QuestionRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}