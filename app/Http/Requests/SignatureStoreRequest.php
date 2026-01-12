<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignatureStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'image' => 'required|file|mimes:png,svg|max:2048',
            'is_default' => 'nullable|boolean',
        ];
    }
}
