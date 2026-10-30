<?php

namespace App\Http\Requests;

/**
 * Validasi body POST /orders (checkout).
 *
 * Hanya menerima user_id; isi keranjang selalu diambil dari cart-service.
 */
class StoreOrderRequest extends ApiFormRequest
{
    /**
     * Aturan validasi untuk membuat pesanan.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|min:1',
        ];
    }

    /**
     * Pesan validasi dalam bahasa Indonesia.
     *
     * Nama field ditulis langsung sebagai "user_id" karena Laravel mengubah
     * "_" menjadi spasi saat memakai placeholder :attribute.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Field user_id wajib diisi',
            'integer' => 'Field user_id harus berupa angka bulat',
            'min' => 'Field user_id minimal bernilai :min',
        ];
    }
}
