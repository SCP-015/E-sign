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
            'file_path' => $this->file_path,
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'status' => $this->status,
            'signed_path' => $this->signed_path,
            'signed_at' => $this->signed_at,
            'qr_position' => $this->qr_position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
