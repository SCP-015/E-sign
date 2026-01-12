<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'file_path' => $this->file_path,
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'file_size_bytes' => $this->file_size_bytes,
            'mime_type' => $this->mime_type,
            'file_type' => $this->file_type,
            'page_count' => $this->page_count,
            'status' => $this->status,
            'verify_token' => $this->verify_token,
            'signed_at' => $this->signed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
