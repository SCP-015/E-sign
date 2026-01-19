<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => 'required|in:admin,member',
            'expiry_days' => 'nullable|integer|min:1|max:30',
            'max_uses' => 'nullable|integer|min:1',
        ];
    }
}
