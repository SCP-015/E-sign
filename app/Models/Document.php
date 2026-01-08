<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_path',
        'signed_path',
        'status',
        'x_coord',
        'y_coord',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
