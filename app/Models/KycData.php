<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class KycData extends Model
{
    use HasUlids;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

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
