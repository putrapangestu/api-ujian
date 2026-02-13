<?php

namespace App\Http\Requests;

/**
 * StudentGroup Request Class
 * 
 * Request ini digunakan untuk validasi data grup siswa
 * 
 * @package App\Http\Requests
 * @author Development Team
 * @version 1.0.0
 */
class StudentGroupRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_students' => 'required|integer|min:1|max:1000',
            'status' => 'nullable|in:active,inactive',
        ];

        // Jika update, tambahkan unique rule untuk name
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $groupId = $this->route('group');
            $rules['name'] = 'required|string|max:255|unique:student_groups,name,' . $groupId;
        } else {
            $rules['name'] = 'required|string|max:255|unique:student_groups';
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
            'name' => 'Nama Grup',
            'description' => 'Deskripsi',
            'max_students' => 'Maksimal Siswa',
            'status' => 'Status',
        ];
    }
}