<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSupplementRequest extends Model
{
    protected $fillable = [
        'created_by',
        'updated_by',
        'user_id',
        'shift_id',
        'segment_order',
        'shift_segment_id',
        'segment_payload',
        'reviewed_by',
        'attendance_id',
        'work_date',
        'requested_checkin_at',
        'requested_checkout_at',
        'reason',
        'status',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'requested_checkin_at' => 'datetime',
        'requested_checkout_at' => 'datetime',
        'segment_payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftSegment()
    {
        return $this->belongsTo(ShiftSegment::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
