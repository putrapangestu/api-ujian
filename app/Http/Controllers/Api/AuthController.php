<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Authentication Controller
 *
 * Controller ini menangani semua operasi authentication
 *
 * @author Development Team
 *
 * @version 1.0.0
 */
class AuthController extends Controller
{
    protected AuthService $authService;

    /**
     * AuthController constructor
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    #[OA\Post(
        path: '/auth/register',
        summary: 'Register user baru',
        tags: ['Auth'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
                    new OA\Property(property: 'password', type: 'string', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', minLength: 8),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message'], 201);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    #[OA\Post(
        path: '/auth/login',
        summary: 'Login user',
        tags: ['Auth'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
                    new OA\Property(property: 'password', type: 'string', minLength: 6),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validatedData());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 401, $result['errors'] ?? null);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Logout user',
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function logout(Request $request)
    {
        $result = $this->authService->logout($request->user());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Profil user saat ini',
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function profile(Request $request)
    {
        $result = $this->authService->profile($request->user());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }

    #[OA\Put(
        path: '/auth/profile',
        summary: 'Update profil user',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function updateProfile(Request $request)
    {
        $result = $this->authService->updateProfile($request->user(), $request->all());

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message'], 400, $result['errors'] ?? null);
    }
}
