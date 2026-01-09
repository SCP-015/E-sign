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
        'original_filename',
        'file_size',
        'mime_type',
        'signature_x',
        'signature_y',
        'signature_width',
        'signature_height',
        'signature_page',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'file_size' => 'integer',
        'signature_page' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getQrPositionAttribute()
    {
        if (!$this->signature_x || !$this->signature_y) {
            return null;
        }

        return [
            'x' => (float) $this->signature_x,
            'y' => (float) $this->signature_y,
            'width' => (float) ($this->signature_width ?? 0.15),
            'height' => (float) ($this->signature_height ?? 0.15),
            'page' => $this->signature_page ?? 1,
        ];
    }

    public function setQrPositionAttribute($value)
    {
        if (is_array($value)) {
            $this->signature_x = $value['x'] ?? null;
            $this->signature_y = $value['y'] ?? null;
            $this->signature_width = $value['width'] ?? 0.15;
            $this->signature_height = $value['height'] ?? 0.15;
            $this->signature_page = $value['page'] ?? 1;
        }
    }
}
