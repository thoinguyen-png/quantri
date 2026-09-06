<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceVerificationModeLog extends Model
{
    protected $fillable = [
        'actor_id',
        'target_user_id',
        'bulk_filter_json',
        'old_mode',
        'new_mode',
        'action',
    ];

    protected $casts = [
        'bulk_filter_json' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
