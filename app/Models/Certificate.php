<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UsesTenantAwareConnection;

class Certificate extends Model
{
    use HasFactory, UsesTenantAwareConnection, HasUlids;

    protected $fillable = [
        'user_id',
        'root_ca_id',
        'certificate_number',
        'public_key_path',
        'certificate_path',
        'private_key_path',
        'subject_name',
        'issuer_name',
        'serial_number',
        'issued_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
