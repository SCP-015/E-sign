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
            'status' => $this->status,
            'signed_path' => $this->signed_path,
            'x_coord' => $this->x_coord,
            'y_coord' => $this->y_coord,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
