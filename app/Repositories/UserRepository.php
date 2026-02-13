<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * User Repository Class
 * 
 * Repository ini menangani semua operasi database terkait User model
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * UserRepository constructor
     * 
     * @param User $model
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Find user by email
     * 
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Find user by email with relations
     * 
     * @param string $email
     * @param array $relations
     * @return User|null
     */
    public function findByEmailWithRelations(string $email, array $relations = []): ?User
    {
        return $this->model->with($relations)->where('email', $email)->first();
    }

    /**
     * Get users by role
     * 
     * @param string $role
     * @return Collection
     */
    public function getByRole(string $role): Collection
    {
        return $this->model->where('role', $role)->get();
    }

    /**
     * Search users by name or email
     * 
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection
    {
        return $this->model
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->limit($limit)
            ->get();
    }
}