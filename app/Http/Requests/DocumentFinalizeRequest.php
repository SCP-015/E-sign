<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentFinalizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qrPlacement.page' => 'nullable|string',
            'qrPlacement.position' => 'nullable|string',
            'qrPlacement.marginBottom' => 'nullable|integer',
            'qrPlacement.size' => 'nullable|integer',
            'qrPlacement.marginRight' => 'nullable|integer',
        ];
    }
}
