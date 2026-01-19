<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotaSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_documents_per_user' => 'required|integer|min:1|max:10000',
            'max_signatures_per_user' => 'required|integer|min:1|max:10000',
            'max_document_size_mb' => 'required|integer|min:1|max:100',
            'max_total_storage_mb' => 'required|integer|min:100|max:100000',
        ];
    }
}
