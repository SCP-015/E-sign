<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycData extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'id_type',
        'id_number',
        'date_of_birth',
        'address',
        'city',
        'province',
        'postal_code',
        'id_photo_path',
        'selfie_photo_path',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
