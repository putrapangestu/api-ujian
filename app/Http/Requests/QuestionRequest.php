<?php

namespace App\Http\Requests;

/**
 * Question Request Class
 *
 * Request ini digunakan untuk validasi data soal
 *
 * @package App\Http\Requests
 * @author Development Team
 * @version 1.0.0
 */
class QuestionRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'exam_id' => 'required|exists:exams,id',
            'category_id' => 'required|exists:question_categories,id',
            'question_group_id' => 'nullable|exists:question_groups,id',
            'question_text' => 'required|string|max:1000',
            'question_type' => 'required|in:multiple_choice,essay,true_false',
            'score' => 'required|integer|min:1|max:100',
        ];

        // Validasi tambahan berdasarkan tipe soal
        if ($this->question_type === 'multiple_choice') {
            $rules['options'] = 'required|array|min:2';
            $rules['options.*.text'] = 'required|string|max:255';
            $rules['options.*.is_correct'] = 'required|boolean';
            $rules['options.*.score'] = 'required|integer|min:0|max:100';
            $rules['options.*.label'] = 'nullable|string|max:10';
        } elseif ($this->question_type === 'true_false') {
            $rules['correct_answer'] = 'required|in:true,false';
        } elseif ($this->question_type === 'essay') {
            $rules['correct_answer'] = 'nullable|string|max:1000';
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
            'exam_id' => 'Ujian',
            'category_id' => 'Kategori',
            'question_group_id' => 'Grup Soal',
            'question_text' => 'Teks Soal',
            'question_type' => 'Tipe Soal',
            'options' => 'Pilihan Jawaban',
            'options.*.text' => 'Teks Pilihan',
            'options.*.is_correct' => 'Jawaban Benar',
            'options.*.score' => 'Nilai Pilihan',
            'options.*.label' => 'Label Pilihan',
            'correct_answer' => 'Jawaban Benar',
            'score' => 'Nilai',
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
            // Validasi tambahan untuk multiple choice
            if ($this->question_type === 'multiple_choice' && $this->options) {
                $options = $this->options ?? [];

                // Check if exactly one correct answer exists
                $correctAnswers = collect($options)->where('is_correct', true)->count();

                if ($correctAnswers === 0) {
                    $validator->errors()->add('options', 'Minimal satu pilihan harus benar.');
                } elseif ($correctAnswers > 1) {
                    $validator->errors()->add('options', 'Hanya satu pilihan yang boleh benar.');
                }

                // Check total score
                $totalScore = collect($options)->sum('score');
                if ($totalScore <= 0) {
                    $validator->errors()->add('options', 'Total nilai harus lebih dari 0.');
                }
            }
        });
    }
}
