<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * User Controller
 *
 * @OA\Tag(
 *     name="Users",
 *     description="API endpoints for managing users"
 * )
 *
 * @package App\Http\Controllers\Api
 * @author Development Team
 * @version 1.0.0
 */
class UserController extends Controller
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Get all users without pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/admin/users/all",
     *     summary="Get all users without pagination",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function listAll(Request $request)
    {
        $users = $this->userRepository->all();

        return $this->successResponse($users, 'Daftar user berhasil diambil');
    }
}

