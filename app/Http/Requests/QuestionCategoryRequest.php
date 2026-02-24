<?php

namespace App\Http\Requests;

/**
 * QuestionCategory Request Class
 *
 * Request ini digunakan untuk validasi data kategori soal
 *
 * @package App\Http\Requests
 * @author Development Team
 * @version 1.0.0
 */
class QuestionCategoryRequest extends BaseRequest
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
            'code' => 'required|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ];

        // Jika update, tambahkan unique rule untuk code
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $categoryId = $this->id;
            $rules['code'] = 'required|string|max:50|unique:question_categories,code,' . $categoryId;
        } else {
            $rules['code'] = 'required|string|max:50|unique:question_categories';
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
            'name' => 'Nama Kategori',
            'description' => 'Deskripsi',
            'code' => 'Kode Kategori',
            'status' => 'Status',
        ];
    }
}
