<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * User Repository Interface
 * 
 * Interface ini mendefinisikan kontrak untuk UserRepository
 * 
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find user by email
     * 
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find user by email with relations
     * 
     * @param string $email
     * @param array $relations
     * @return User|null
     */
    public function findByEmailWithRelations(string $email, array $relations = []): ?User;

    /**
     * Get users by role
     * 
     * @param string $role
     * @return Collection
     */
    public function getByRole(string $role): Collection;

    /**
     * Search users by name or email
     * 
     * @param string $search
     * @param int $limit
     * @return Collection
     */
    public function search(string $search, int $limit = 10): Collection;
}