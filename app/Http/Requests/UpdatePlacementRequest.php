<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
            'w' => 'nullable|numeric',
            'h' => 'nullable|numeric',
        ];
    }
}
