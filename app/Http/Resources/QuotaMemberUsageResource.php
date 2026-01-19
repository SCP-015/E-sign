<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotaMemberUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'userId' => $this->resource['userId'] ?? null,
            'user' => $this->resource['user'] ?? null,
            'role' => $this->resource['role'] ?? null,
            'documentsUploaded' => $this->resource['documentsUploaded'] ?? null,
            'signaturesCreated' => $this->resource['signaturesCreated'] ?? null,
            'storageUsedMb' => $this->resource['storageUsedMb'] ?? null,
            'override' => $this->resource['override'] ?? null,
            'effectiveLimits' => $this->resource['effectiveLimits'] ?? null,
            'documentsRemaining' => $this->resource['documentsRemaining'] ?? null,
            'signaturesRemaining' => $this->resource['signaturesRemaining'] ?? null,
        ];
    }
}
