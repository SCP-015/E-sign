<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $createdBy = $this->whenLoaded('createdBy');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'role' => $this->role,
            'expiresAt' => $this->expires_at?->toISOString(),
            'isExpired' => $this->expires_at?->isPast() ?? false,
            'maxUses' => $this->max_uses,
            'usedCount' => $this->used_count,
            'isValid' => $this->isValid(),
            'createdBy' => [
                'id' => $createdBy?->id,
                'name' => $createdBy?->name,
            ],
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
