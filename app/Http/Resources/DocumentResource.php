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
            'user_id' => $this->user_id,
            'userId' => $this->user_id,
            'title' => $this->title,
            'file_path' => $this->file_path,
            'filePath' => $this->file_path,
            'original_filename' => $this->original_filename,
            'originalFilename' => $this->original_filename,
            'file_size' => $this->file_size,
            'fileSize' => $this->file_size,
            'file_size_bytes' => $this->file_size_bytes,
            'mime_type' => $this->mime_type,
            'mimeType' => $this->mime_type,
            'file_type' => $this->file_type,
            'status' => $this->status,
            'page_count' => $this->page_count,
            'pageCount' => $this->page_count,
            'verify_token' => $this->verify_token,
            'signed_at' => $this->signed_at,
            'signedAt' => $this->signed_at?->toIso8601String(),
            'completed_at' => $this->completed_at,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at,
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'signers' => $this->signers->map(fn($s) => [
                'id' => $s->id,
                'userId' => $s->user_id,
                'user_id' => $s->user_id,
                'email' => $s->email,
                'name' => $s->name,
                'status' => $s->status,
                'signedAt' => $s->signed_at?->toIso8601String(),
                'signed_at' => $s->signed_at,
            ]),
        ];
    }
}
