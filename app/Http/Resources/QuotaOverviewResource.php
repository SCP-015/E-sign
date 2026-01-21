<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotaOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quotaSetting = $this->resource['quotaSetting'] ?? null;
        $usage = $this->resource['usage'] ?? [];

        return [
            'quotaSettings' => $quotaSetting ? new QuotaSettingsResource($quotaSetting) : null,
            'usage' => QuotaMemberUsageResource::collection($usage)->toArray($request),
        ];
    }
}
