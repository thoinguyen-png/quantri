<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickOnboardingBatch extends Model
{
    protected $fillable = [
        'created_by',
        'branch_id',
        'entries_count',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function entries()
    {
        return $this->hasMany(QuickOnboardingEntry::class);
    }
}
