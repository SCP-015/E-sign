<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlacementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signerUserId' => 'required|exists:users,id',
            'placements' => 'required|array|min:1',
            'placements.*.page' => 'required|integer|min:1',
            'placements.*.x' => 'required|numeric',
            'placements.*.y' => 'required|numeric',
            'placements.*.w' => 'required|numeric',
            'placements.*.h' => 'required|numeric',
            'placements.*.signatureId' => 'required|exists:signatures,id',
        ];
    }
}
