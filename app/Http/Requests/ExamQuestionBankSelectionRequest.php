<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ExamQuestionBankSelection Request
 * 
 * Request ini digunakan untuk validasi pemilihan soal dari bank soal
 * 
 * @package App\Http\Requests
 * @author Development Team
 * @version 1.0.0
 */
class ExamQuestionBankSelectionRequest extends FormRequest
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
        return [
            'question_banks' => 'required|array|min:1',
            'question_banks.*.question_group_id' => 'required|integer|exists:question_groups,id',
            'question_banks.*.question_count' => 'required|integer|min:1|max:1000',
            'question_banks.*.random_selection' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question_banks.required' => 'Daftar bank soal wajib diisi',
            'question_banks.array' => 'Format bank soal tidak valid',
            'question_banks.min' => 'Minimal harus ada 1 bank soal',
            'question_banks.*.question_group_id.required' => 'ID grup soal wajib diisi',
            'question_banks.*.question_group_id.integer' => 'ID grup soal harus berupa angka',
            'question_banks.*.question_group_id.exists' => 'Grup soal tidak ditemukan',
            'question_banks.*.question_count.required' => 'Jumlah soal wajib diisi',
            'question_banks.*.question_count.integer' => 'Jumlah soal harus berupa angka',
            'question_banks.*.question_count.min' => 'Jumlah soal minimal 1',
            'question_banks.*.question_count.max' => 'Jumlah soal maksimal 1000',
            'question_banks.*.random_selection.boolean' => 'Pilihan random harus berupa true/false',
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
            'question_banks' => 'bank soal',
            'question_banks.*.question_group_id' => 'ID grup soal',
            'question_banks.*.question_count' => 'jumlah soal',
            'question_banks.*.random_selection' => 'pilihan random',
        ];
    }
}