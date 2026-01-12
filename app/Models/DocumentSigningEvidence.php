<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSigningEvidence extends Model
{
    use HasFactory;

    protected $table = 'document_signing_evidences';

    protected $fillable = [
        'document_id',
        'certificate_id',
        'certificate_number',
        'certificate_fingerprint_sha256',
        'certificate_serial',
        'certificate_subject',
        'certificate_issuer',
        'certificate_not_before',
        'certificate_not_after',
        'certificate_pem',
        'signed_at',
        'tsa_url',
        'tsa_at',
        'tsa_token',
        'ocsp_response',
        'crl',
    ];

    protected $casts = [
        'certificate_not_before' => 'datetime',
        'certificate_not_after' => 'datetime',
        'signed_at' => 'datetime',
        'tsa_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
