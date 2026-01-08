<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'private_key_path',
        'public_key_path',
        'certificate_path',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
