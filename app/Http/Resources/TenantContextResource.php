<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenant = $this->resource['tenant'] ?? null;

        if (!$tenant) {
            return [];
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'role' => $this->resource['role'] ?? 'member',
        ];
    }
}
