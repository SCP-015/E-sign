<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPortalBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'banner' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }
}
