<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

/**
 * Auth Service Class
 *
 * Service ini menangani semua operasi authentication
 *
 * @package App\Services
 * @author Development Team
 * @version 1.0.0
 */
class AuthService extends BaseService
{
    /**
     * @var UserRepositoryInterface
     */
    protected UserRepositoryInterface $userRepository;

    /**
     * AuthService constructor
     *
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Register new user
     *
     * @param array $data Validated data from RegisterRequest
     * @return array
     */
    public function register(array $data): array
    {
        try {
            // Hash password
            $data['password'] = Hash::make($data['password']);

            // Create user
            $user = $this->userRepository->create($data);

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ], 'Registrasi berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Registrasi gagal: ' . $e->getMessage());
        }
    }

    /**
     * Login user
     *
     * @param array $credentials Validated data from LoginRequest
     * @return array
     */
    public function login(array $credentials): array
    {
        try {
            // Attempt login
            if (!Auth::attempt($credentials)) {
                return $this->errorResponse('Email atau password salah', null, 401);
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
                'roles' => $user->roles->pluck('name')->toArray(),
                'token_type' => 'Bearer'
            ], 'Login berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Login gagal: ' . $e->getMessage());
        }
    }

    /**
     * Logout user
     *
     * @param User $user
     * @return array
     */
    public function logout(User $user): array
    {
        try {
            // Revoke token
            $user->currentAccessToken()->delete();

            return $this->successResponse(null, 'Logout berhasil');

        } catch (\Exception $e) {
            return $this->errorResponse('Logout gagal: ' . $e->getMessage());
        }
    }

    /**
     * Get user profile
     *
     * @param User $user
     * @return array
     */
    public function profile(User $user): array
    {
        try {
            return $this->successResponse([
                'user' => $user,
                'roles' => $user->roles->pluck('name')->toArray(),
            ], 'Profile berhasil diambil');

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil profile: ' . $e->getMessage());
        }
    }

    /**
     * Update user profile
     *
     * @param User $user
     * @param array $data Validated data
     * @return array
     */
    public function updateProfile(User $user, array $data): array
    {
        try {
            // Update user
            $updated = $this->userRepository->update($user->id, $data);

            if (!$updated) {
                return $this->errorResponse('Gagal update profile');
            }

            // Get updated user
            $updatedUser = $this->userRepository->find($user->id);

            return $this->successResponse($updatedUser, 'Profile berhasil diupdate');

        } catch (\Exception $e) {
            return $this->errorResponse('Update profile gagal: ' . $e->getMessage());
        }
    }
}
