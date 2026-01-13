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
            'signerUserId' => 'nullable|exists:users,id',
            'email' => 'nullable|email',
            'placements' => 'required|array|min:1',
            'placements.*.page' => 'required|integer|min:1',
            'placements.*.x' => 'required|numeric',
            'placements.*.y' => 'required|numeric',
            'placements.*.w' => 'required|numeric',
            'placements.*.h' => 'required|numeric',
            'placements.*.signatureId' => 'nullable|exists:signatures,id',
        ];
    }

    public function messages(): array
    {
        return [
            'placements.required' => 'At least one placement is required',
            'placements.array' => 'Placements must be an array',
            'placements.min' => 'At least one placement is required',
            'placements.*.page.required' => 'Page number is required for each placement',
            'placements.*.page.integer' => 'Page number must be an integer',
            'placements.*.page.min' => 'Page number must be at least 1',
            'placements.*.x.required' => 'X coordinate is required',
            'placements.*.x.numeric' => 'X coordinate must be numeric',
            'placements.*.y.required' => 'Y coordinate is required',
            'placements.*.y.numeric' => 'Y coordinate must be numeric',
            'placements.*.w.required' => 'Width is required',
            'placements.*.w.numeric' => 'Width must be numeric',
            'placements.*.h.required' => 'Height is required',
            'placements.*.h.numeric' => 'Height must be numeric',
            'placements.*.signatureId.exists' => 'Invalid signature ID',
        ];
    }
}
