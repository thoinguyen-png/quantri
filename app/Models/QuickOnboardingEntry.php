<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickOnboardingEntry extends Model
{
    protected $fillable = [
        'quick_onboarding_batch_id',
        'demo_code',
        'expected_name',
        'avatar_path',
        'created_by',
        'branch_id',
        'intended_role',
        'token',
        'status',
        'sent_at',
        'completed_at',
        'completed_user_id',
        'submitted_payload',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'submitted_payload' => 'array',
            'used_at' => 'datetime',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(QuickOnboardingBatch::class, 'quick_onboarding_batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function completedUser()
    {
        return $this->belongsTo(User::class, 'completed_user_id');
    }

    public function getInviteUrlAttribute(): string
    {
        return route('quick-onboarding.public.show', $this->token);
    }
}
