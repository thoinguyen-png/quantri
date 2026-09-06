<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'start_at',
        'end_at',
        'late_after_minutes',
        'checkin_open_before_minutes',
        'checkin_close_after_minutes',
        'checkout_min_after_checkin_minutes',
        'checkout_close_after_shift_end_minutes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function assignments()
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    public function segments()
    {
        return $this->hasMany(ShiftSegment::class)->orderBy('segment_order');
    }

    public function customerRatings()
    {
        return $this->hasMany(CustomerRating::class);
    }
}
