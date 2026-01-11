<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'file_type',
        'file_size_bytes',
        'page_count',
        'signed_path',
        'final_pdf_path',
        'verify_token',
        'status',
        'signed_at',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'signed_at' => 'datetime',
        'file_size' => 'integer',
        'file_size_bytes' => 'integer',
        'page_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signers()
    {
        return $this->hasMany(DocumentSigner::class);
    }

    public function placements()
    {
        return $this->hasMany(SignaturePlacement::class);
    }

    public function signingEvidence()
    {
        return $this->hasOne(DocumentSigningEvidence::class);
    }

    public function isAllSigned()
    {
        return $this->signers()->where('status', 'PENDING')->count() === 0;
    }
}
