<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->whenLoaded('user');

        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'name' => $user?->name,
            'email' => $user?->email,
            'avatar' => $user?->avatar,
            'role' => $this->role,
            'isOwner' => (bool) $this->is_owner,
            'joinedAt' => $this->joined_at?->toISOString(),
        ];
    }
}
