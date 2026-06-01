<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base Request Class
 * 
 * Class ini menyediakan fungsi-fungsi dasar untuk request validation
 * dan dapat di-extend oleh request-request lainnya
 * 
 * @package App\Http\Requests
 * @author Development Team
 * @version 1.0.0
 */
abstract class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'email' => ':attribute harus berupa email yang valid.',
            'unique' => ':attribute sudah digunakan.',
            'min' => ':attribute minimal harus :min karakter.',
            'max' => ':attribute maksimal harus :max karakter.',
            'confirmed' => 'Konfirmasi :attribute tidak cocok.',
            'numeric' => ':attribute harus berupa angka.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'boolean' => ':attribute harus berupa true atau false.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'array' => ':attribute harus berupa array.',
            'file' => ':attribute harus berupa file.',
            'image' => ':attribute harus berupa gambar.',
            'mimes' => ':attribute harus berupa file dengan tipe: :values.',
            'in' => ':attribute yang dipilih tidak valid.',
            'exists' => ':attribute yang dipilih tidak valid.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $formattedErrors = [];
        
        foreach ($errors as $field => $messages) {
            $formattedErrors[$field] = $messages[0];
        }
        
        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'success' => false,
                'message' => 'Data yang Anda masukkan tidak sesuai ketentuan. Silakan periksa kembali.',
                'errors' => $formattedErrors
            ], 422)
        );
    }

    /**
     * Get the validated data from the request.
     *
     * @return array
     */
    public function validatedData(): array
    {
        return $this->validated();
    }

    /**
     * Get only specific fields from validated data.
     *
     * @param array $fields Fields yang ingin diambil
     * @return array
     */
    public function onlyValidated(array $fields): array
    {
        return array_intersect_key($this->validated(), array_flip($fields));
    }
}