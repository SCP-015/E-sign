<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'fullName' => $this->full_name,
            'idType' => $this->id_type,
            'idNumber' => $this->id_number,
            'dateOfBirth' => $this->date_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'postalCode' => $this->postal_code,
            'idPhotoPath' => $this->id_photo_path,
            'selfiePhotoPath' => $this->selfie_photo_path,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
