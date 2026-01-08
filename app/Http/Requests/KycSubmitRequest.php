<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KycSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'id_type' => 'required|string|in:ktp,passport,sim,other',
            'id_number' => 'required|string|max:255',
            'date_of_birth' => 'required|date_format:Y-m-d',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'id_photo' => 'required|file|mimes:jpeg,jpg,png|max:5120',
            'selfie_photo' => 'required|file|mimes:jpeg,jpg,png|max:5120',
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('id_type')) {
            $this->merge([
                'id_type' => strtolower($this->input('id_type')),
            ]);
        }
    }
}
