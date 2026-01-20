<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    use HasFactory, HasUlids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'user_id',
        'name',
        'image_path',
        'image_type',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper: Check apakah signature personal (portable)
     */
    public function isPersonal(): bool
    {
        return true;
    }

    /**
     * Helper: Check apakah signature portable
     */
    public function isPortable(): bool
    {
        return true;
    }
}
