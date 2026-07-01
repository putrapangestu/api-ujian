<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = Auth::id();

        return [
            'name'     => 'nullable|string|max:255',
            'email'    => 'nullable|email|max:255|unique:users,email,' . $userId,
            'password' => 'nullable|string|min:8|confirmed',
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max'            => 'Nama maksimal 255 karakter',
            'email.email'         => 'Format email tidak valid',
            'email.unique'        => 'Email sudah digunakan oleh pengguna lain',
            'email.max'           => 'Email maksimal 255 karakter',
            'password.min'        => 'Password minimal 8 karakter',
            'password.confirmed'  => 'Konfirmasi password tidak cocok',
            'avatar.image'        => 'File harus berupa gambar',
            'avatar.mimes'        => 'Format gambar harus jpeg, png, jpg, atau gif',
            'avatar.max'          => 'Ukuran gambar maksimal 2MB',
        ];
    }
}
