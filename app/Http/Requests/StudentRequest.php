<?php

namespace App\Http\Requests;

/**
 * Student Request Class
 *
 * Request ini digunakan untuk validasi data siswa
 *
 * @package App\Http\Requests
 * @author Development Team
 * @version 1.0.0
 */
class StudentRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'nisn' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'group_id' => 'required|exists:student_groups,id',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:active,inactive,graduated,dropped',
        ];

        // Jika update, tambahkan unique rule untuk nisn dan email
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $studentId = $this->route('student');
            $rules['nisn'] = 'required|string|max:20|unique:students,nisn,' . $studentId;
            $rules['email'] = 'required|string|email|max:255|unique:students,email,' . $studentId;
        } else {
            $rules['nisn'] = 'required|string|max:20|unique:students';
            $rules['email'] = 'required|string|email|max:255|unique:students';
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nisn' => 'NISN',
            'name' => 'Nama Siswa',
            'email' => 'Email',
            'phone' => 'Nomor Telepon',
            'address' => 'Alamat',
            'group_id' => 'Grup',
            'user_id' => 'User',
            'status' => 'Status',
        ];
    }
}
