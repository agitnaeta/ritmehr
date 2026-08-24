<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NationalHolidayRequest extends FormRequest
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
        // Backpack mengirim id pada update; abaikan baris itu sendiri saat cek unique.
        $id = $this->input('id');

        return [
            'date' => [
                'required', 'date',
                Rule::unique('national_holidays', 'date')->ignore($id),
            ],
            'info' => 'required|string|max:255',
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
            'date' => 'tanggal libur',
            'info' => 'keterangan',
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
            'date.required' => 'Tanggal libur wajib diisi.',
            'date.date'     => 'Tanggal libur tidak valid.',
            'date.unique'   => 'Tanggal libur ini sudah terdaftar.',
            'info.required' => 'Keterangan libur wajib diisi.',
        ];
    }
}
