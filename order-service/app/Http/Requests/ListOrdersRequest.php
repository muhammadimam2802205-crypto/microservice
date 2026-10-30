<?php

namespace App\Http\Requests;

/**
 * Validasi query string GET /orders/user/{userId}.
 *
 * Filter status bersifat opsional; bila diisi harus PENDING atau PAID.
 */
class ListOrdersRequest extends ApiFormRequest
{
    /**
     * Aturan validasi untuk filter daftar pesanan.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'status' => 'nullable|in:PENDING,PAID',
        ];
    }

    /**
     * Pesan validasi dalam bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status harus PENDING atau PAID',
        ];
    }
}
