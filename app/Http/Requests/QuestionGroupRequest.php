<?php

namespace App\Http\Requests;

/**
 * QuestionGroup Request Class
 *
 * Request ini digunakan untuk validasi data grup soal/bank soal
 *
 * @package App\Http\Requests
 * @author Development Team
 * @version 1.0.0
 */
class QuestionGroupRequest extends BaseRequest
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
            'status' => 'nullable|in:active,inactive,draft',
            'question_category_id' => 'required|uuid|exists:question_categories,id',
        ];

        // Jika update, tambahkan unique rule untuk code
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $groupId = $this->id;
            $rules['code'] = 'required|string|max:50|unique:question_groups,code,' . $groupId;
        } else {
            $rules['code'] = 'required|string|max:50|unique:question_groups';
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
            'name' => 'Nama Grup Soal',
            'description' => 'Deskripsi',
            'code' => 'Kode Grup',
            'status' => 'Status',
            'question_category_id' => 'Kategori Soal',
        ];
    }
}
