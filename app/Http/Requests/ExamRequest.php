<?php

namespace App\Http\Requests;

/**
 * Exam Request Class
 *
 * Request ini digunakan untuk validasi data ujian
 *
 * @package App\Http\Requests
 * @author Development Team
 * @version 1.0.0
 */
class ExamRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'required|integer|min:1|max:300',
            'total_questions' => 'nullable',
            'passing_score' => 'required|integer|min:0|max:100',
            'start_date' => 'nullable|date|after_or_equal:now',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'question_groups' => 'nullable|array',
            'question_groups.*' => 'exists:question_groups,id',
            'student_groups' => 'nullable|array',
            'student_groups.*' => 'exists:student_groups,id',
        ];

        // Jika update, validasi tanggal bisa lebih fleksibel
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['start_date'] = 'nullable|date';
            $rules['end_date'] = 'nullable|date|after:start_date';
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
            'title' => 'Judul Ujian',
            'description' => 'Deskripsi',
            'duration' => 'Durasi (menit)',
            'total_questions' => 'Jumlah Soal',
            'passing_score' => 'Nilai Kelulusan',
            'start_date' => 'Tanggal Mulai',
            'end_date' => 'Tanggal Berakhir',
            'question_groups' => 'Grup Soal',
            'student_groups' => 'Grup Siswa',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validasi tambahan untuk tanggal
            if ($this->start_date && $this->end_date) {
                if (strtotime($this->end_date) <= strtotime($this->start_date)) {
                    $validator->errors()->add('end_date', 'Tanggal berakhir harus setelah tanggal mulai.');
                }
            }
        });
    }
}
