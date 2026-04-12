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
            'nisn' => 'nullable|string|max:20',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'group_id' => 'sometimes|exists:student_groups,id',
            'birth_date' => 'required|date',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:active,inactive,graduated,dropped',
        ];

        // Jika update, tambahkan unique rule untuk nisn dan email
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $studentId = $this->id;
            $rules['nisn'] = 'nullable|string|max:20|unique:students,nisn,' . $studentId . ',id,deleted_at,NULL';
            $rules['email'] = 'required|string|email|max:255|unique:users,email,' . $studentId;
        } else {
            $rules['nisn'] = 'nullable|string|max:20|unique:students,deleted_at,NULL';
            $rules['email'] = 'required|string|email|max:255|unique:users,deleted_at,NULL';
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
            'birth_date' => 'Tanggal Lahir',
            'user_id' => 'User',
            'status' => 'Status',
        ];
    }
}
