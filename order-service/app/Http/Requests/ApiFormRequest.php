<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base Form Request untuk seluruh endpoint API order-service.
 *
 * Semua kegagalan validasi dibalas JSON 422 dengan pesan seragam, bukan
 * redirect/HTML seperti perilaku bawaan FormRequest.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * Semua request diizinkan; autentikasi di luar ruang lingkup UTS.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Melempar response 422 berisi message seragam dan detail errors per field.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Data yang dikirim tidak valid',
            'errors' => $validator->errors(),
        ], 422));
    }
}
