<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalDeviceMarker extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'role',
        'last_seen_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
