<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'string',
                'unique:salaries,user_id'
            ],
            'basic_salary' => 'required|integer|min:0',
            'amount' => 'nullable|integer',
            'overtime_amount' => 'required|integer',
            'overtime_type' => 'required|in:flat,hour',
            'fine_type' => 'required',
        ];
    }
    public function rulesUpdate($userId): array
    {
        return [
            'user_id' => [
                'required',
                'string',
                Rule::unique('salaries','user_id')->ignore($userId)
            ],
            'basic_salary' => 'required|integer|min:0',
            'amount' => 'nullable|integer',
            'overtime_amount' => 'required|integer',
            'overtime_type' => 'required|in:flat,hour',
            'fine_type' => 'required',
        ];
    }



    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'user_id.required' => 'Kolom user ID harus diisi.',
            'user_id.string' => 'Kolom user ID harus berupa teks.',
            'user_id.unique' => 'Kolom user Sudah pernah diset silahkan ubah di menu edit.',
            'amount.required' => 'Kolom amount harus diisi.',
            'amount.integer' => 'Kolom amount harus berupa bilangan bulat.',
            'overtime_amount.required' => 'Kolom overtime amount harus diisi.',
            'overtime_amount.integer' => 'Kolom overtime amount harus berupa bilangan bulat.',
            'overtime_type.required' => 'Kolom overtime type harus diisi.',
            'overtime_type.in' => 'Kolom overtime type harus salah satu dari: :values.',
            'fine_type.required' => 'Jenis denda harus di isi.',
        ];
    }
}
