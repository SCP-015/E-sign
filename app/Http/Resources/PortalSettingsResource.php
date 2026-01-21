<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $logoPath = (string) ($this->logo ?? '');
        $bannerPath = (string) ($this->banner ?? '');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $logoPath,
            'banner' => $bannerPath,
            'logoUrl' => $logoPath !== '' ? asset('storage/' . ltrim($logoPath, '/')) : null,
            'bannerUrl' => $bannerPath !== '' ? asset('storage/' . ltrim($bannerPath, '/')) : null,
            'website' => $this->website,
            'phone' => $this->phone,
            'address' => $this->address,
            'facebook' => $this->facebook,
            'twitter' => $this->twitter,
            'instagram' => $this->instagram,
            'linkedin' => $this->linkedin,
        ];
    }
}
