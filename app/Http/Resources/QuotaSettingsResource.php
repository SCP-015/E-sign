<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotaSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'maxDocumentsPerUser' => $this->max_documents_per_user,
            'maxSignaturesPerUser' => $this->max_signatures_per_user,
            'maxDocumentSizeMb' => $this->max_document_size_mb,
            'maxTotalStorageMb' => $this->max_total_storage_mb,
        ];
    }
}
