<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

/**
 * Login Request Class
 * 
 * Request ini digunakan untuk validasi data login user
 * 
 * @package App\Http\Requests\Auth
 * @author Development Team
 * @version 1.0.0
 */
class LoginRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => 'Username',
            'password' => 'Password',
        ];
    }
}