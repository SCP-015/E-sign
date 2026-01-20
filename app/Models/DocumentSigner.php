<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

use App\Traits\UsesTenantAwareConnection;

class DocumentSigner extends Model
{
    use HasUlids, UsesTenantAwareConnection;
    protected $fillable = [
        'document_id',
        'user_id',
        'name',
        'email',
        'invite_token',
        'invite_expires_at',
        'invite_accepted_at',
        'order',
        'status',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'invite_expires_at' => 'datetime',
        'invite_accepted_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function placements()
    {
        return $this->hasMany(SignaturePlacement::class, 'signer_id');
    }
}
