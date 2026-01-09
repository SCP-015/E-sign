<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignaturePlacement extends Model
{
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
