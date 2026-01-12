<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSigner extends Model
{
    protected $fillable = [
        'document_id',
        'user_id',
        'name',
        'email',
        'invite_token',
        'order',
        'status',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
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
