<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

use App\Traits\UsesTenantAwareConnection;

class SignaturePlacement extends Model
{
    use HasUlids, UsesTenantAwareConnection;
    protected $fillable = [
        'document_id',
        'signer_id',
        'signature_id',
        'page',
        'x',
        'y',
        'w',
        'h',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function signer()
    {
        return $this->belongsTo(DocumentSigner::class, 'signer_id');
    }

    public function signature()
    {
        return $this->belongsTo(Signature::class);
    }
}
