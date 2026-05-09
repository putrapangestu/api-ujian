<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
            // Cek username yang berupa nama lengkap tanpa spasi (lowercase)
            $usernameInput = strtolower(str_replace(' ', '', $credentials['username']));

            /** @var \App\Models\User|null $user */
            $user = User::whereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$usernameInput])->first();
            Log::info($user);

            // Attempt login check password
            if (!$user) {
                return $this->errorResponse('Username salah', null, 401);
            }

            if (!Auth::attempt(['email' => $user->email, 'password' => $credentials['password']])) {
                return $this->errorResponse('Password salah', null, 401);
            }

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
