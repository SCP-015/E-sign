<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('documentId') && !$this->has('document_id')) {
            $this->merge(['document_id' => $this->input('documentId')]);
        }
    }

    public function rules(): array
    {
        return [
            'document_id' => 'required|exists:documents,id',
        ];
    }
}
