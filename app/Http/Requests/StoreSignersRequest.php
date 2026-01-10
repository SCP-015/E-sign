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
            'signers.*.userId' => 'required|exists:users,id',
            'signers.*.name' => 'required|string|max:255',
            'signers.*.order' => 'nullable|integer',
        ];
    }
}
