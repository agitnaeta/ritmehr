<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoanRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            // min:1 — tanpa ini nominal nol dan negatif ikut tersimpan, dan
            // kasbon negatif justru menambah gaji lewat kolom `loan_cut`.
            'amount'  => 'required|integer|min:1',
            'date'    => 'required|date',
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
            'user_id.required' => 'Karyawan harus dipilih.',
            'user_id.exists'   => 'Karyawan tidak ditemukan.',
            'amount.required'  => 'Nominal kasbon harus diisi.',
            'amount.integer'   => 'Nominal kasbon harus berupa bilangan bulat.',
            'amount.min'       => 'Nominal kasbon minimal Rp 1.',
            'date.required'    => 'Tanggal kasbon harus diisi.',
            'date.date'        => 'Tanggal kasbon tidak valid.',
        ];
    }
}
