<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotaUserOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_documents_per_user' => 'nullable|integer|min:1|max:10000',
            'max_signatures_per_user' => 'nullable|integer|min:1|max:10000',
            'max_total_storage_mb' => 'nullable|integer|min:100|max:100000',
        ];
    }
}
