<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSignersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signers' => 'required|array|min:1',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'required|string|max:255',
            'signers.*.order' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'signers.required' => 'At least one signer is required',
            'signers.array' => 'Signers must be an array',
            'signers.min' => 'At least one signer is required',
            'signers.*.email.required' => 'Signer email is required',
            'signers.*.email.email' => 'Signer email must be a valid email address',
            'signers.*.name.required' => 'Signer name is required',
            'signers.*.name.string' => 'Signer name must be a string',
            'signers.*.name.max' => 'Signer name must not exceed 255 characters',
            'signers.*.order.integer' => 'Signer order must be an integer',
        ];
    }
}
