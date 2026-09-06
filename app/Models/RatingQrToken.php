<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatingQrToken extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'token_ciphertext',
        'enabled',
        'revoked_at',
        'regenerated_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'revoked_at' => 'datetime',
            'regenerated_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function customerRatings()
    {
        return $this->hasMany(CustomerRating::class);
    }
}
