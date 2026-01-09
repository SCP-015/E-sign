<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQrPositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'x' => 'required|numeric|min:0|max:1',
            'y' => 'required|numeric|min:0|max:1',
            'width' => 'required|numeric|min:0.01|max:0.5', // max 50% of page width
            'height' => 'required|numeric|min:0.01|max:0.5', // max 50% of page height
            'page' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'x.required' => 'X coordinate is required',
            'x.min' => 'X coordinate must be between 0 and 1 (0% to 100%)',
            'x.max' => 'X coordinate must be between 0 and 1 (0% to 100%)',
            'y.required' => 'Y coordinate is required',
            'y.min' => 'Y coordinate must be between 0 and 1 (0% to 100%)',
            'y.max' => 'Y coordinate must be between 0 and 1 (0% to 100%)',
            'width.min' => 'Width must be at least 1% of page width',
            'width.max' => 'Width must not exceed 50% of page width',
            'height.min' => 'Height must be at least 1% of page height',
            'height.max' => 'Height must not exceed 50% of page height',
            'page.min' => 'Page number must be at least 1',
        ];
    }
}
