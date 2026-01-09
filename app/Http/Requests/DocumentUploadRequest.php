<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf|max:10240', // max 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File is required',
            'file.file' => 'The uploaded file must be a valid file',
            'file.mimes' => 'The file must be a PDF (application/pdf). Uploaded mime type: ' . ($this->file('file') ? $this->file('file')->getMimeType() : 'unknown'),
            'file.max' => 'The file must not be greater than 10240 kilobytes (10 MB). File size: ' . ($this->file('file') ? round($this->file('file')->getSize() / 1024, 2) . ' KB' : 'unknown'),
        ];
    }
}
