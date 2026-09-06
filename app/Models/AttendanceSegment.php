<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSegment extends Model
{
    protected $fillable = [
        'attendance_id', 'segment_order', 'checkin_at', 'checkout_at',
        'checkin_latitude', 'checkin_longitude', 'checkout_latitude', 'checkout_longitude',
        'late_minutes', 'early_leave_minutes', 'worked_minutes',
    ];

    protected $casts = [
        'checkin_at' => 'datetime',
        'checkout_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function customerRatings()
    {
        return $this->hasMany(CustomerRating::class);
    }
}
