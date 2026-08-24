<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoanPaymentRequest extends FormRequest
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
            // min:1 — tanpa ini nominal nol dan negatif ikut tersimpan.
            'amount'  => ['required', 'integer', 'min:1', $this->tidakMelebihiSisa()],
            'date'    => 'required|date',
        ];
    }

    /**
     * Pembayaran tidak boleh melampaui sisa kasbon karyawan.
     *
     * Tanpa ini sisa tagihan bisa jadi negatif, dan angka negatif itu berbalik
     * menjadi tambahan gaji lewat kolom `loan_cut` saat rekap dihitung.
     */
    private function tidakMelebihiSisa(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $userId = $this->input('user_id');
            if (! $userId || ! is_numeric($value)) {
                return;
            }

            $kasbon  = (int) \App\Models\Loan::where('user_id', $userId)->sum('amount');
            $dibayar = (int) \App\Models\LoanPayment::where('user_id', $userId)
                ->when($this->input('id'), fn ($q, $id) => $q->where('id', '!=', $id))
                ->sum('amount');
            $sisa = $kasbon - $dibayar;

            if ($sisa <= 0) {
                $fail('Karyawan ini tidak punya sisa kasbon yang perlu dibayar.');

                return;
            }

            if ((int) $value > $sisa) {
                $fail('Pembayaran melebihi sisa kasbon (sisa: Rp ' . number_format($sisa, 0, ',', '.') . ').');
            }
        };
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
            'amount.required'  => 'Nominal pembayaran harus diisi.',
            'amount.integer'   => 'Nominal pembayaran harus berupa bilangan bulat.',
            'amount.min'       => 'Nominal pembayaran minimal Rp 1.',
            'date.required'    => 'Tanggal pembayaran harus diisi.',
            'date.date'        => 'Tanggal pembayaran tidak valid.',
        ];
    }
}
