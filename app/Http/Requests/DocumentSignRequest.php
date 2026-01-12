<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature_id' => 'required|integer|exists:signatures,id',
            'signature_position' => 'required|array',
            'signature_position.x' => 'required|numeric|min:0|max:1',
            'signature_position.y' => 'required|numeric|min:0|max:1',
            'signature_position.width' => 'required|numeric|min:0.01|max:0.5',
            'signature_position.height' => 'required|numeric|min:0.01|max:0.5',
            'signature_position.page' => 'required|integer|min:1',
        ];
    }
}
