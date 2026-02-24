<?php

use OpenApi\Annotations as OA;

/**
 * Swagger Configuration
 *
 * File ini berisi konfigurasi dan annotations untuk Swagger API Documentation
 *
 * @package App\Http\Controllers
 * @author Development Team
 * @version 1.0.0
 */

/**
 * @OA\Info(
 *     title="API Ujian CPNS",
 *     version="1.0.0",
 *     description="API untuk aplikasi ujian CPNS dengan fitur authentication, ujian, dan manajemen",
 *     @OA\Contact(
 *         email="admin@ujian-cpns.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Masukkan token JWT dengan format: Bearer {token}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/auth/register"
 * )
 *
 * @OA\PathItem(
 *     path="/api/auth/login"
 * )
 *
 * @OA\PathItem(
 *     path="/api/auth/logout"
 * )
 *
 * @OA\PathItem(
 *     path="/api/auth/profile"
 * )
 *
 * @OA\PathItem(
 *     path="/api/exams"
 * )
 *
 * @OA\PathItem(
 *     path="/api/exams/{id}"
 * )
 *
 * @OA\PathItem(
 *     path="/api/exams/{id}/start"
 * )
 *
 * @OA\PathItem(
 *     path="/api/admin/exams"
 * )
 *
 * @OA\PathItem(
 *     path="/api/admin/exams/{id}"
 * )
 *
 * @OpenApi\Annotations\PathItem(
 *     path="/health"
 * )
 */
