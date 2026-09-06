<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'shift_id',
        'work_date',
        'checkin_at',
        'checkout_at',
        'checkin_latitude',
        'checkin_longitude',
        'checkout_latitude',
        'checkout_longitude',
        'late_minutes',
        'overtime_minutes',
        'overtime_hours',
        'worked_minutes',
        'work_day',
        'penalty_workday',
        'status',
        'is_locked',
        'locked_at',
        'locked_by',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'checkin_at' => 'datetime',
        'checkout_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function adjustmentLogs()
    {
        return $this->hasMany(AttendanceAdjustmentLog::class);
    }

    public function scopeForWorkDate($query, $date)
    {
        $workDate = Carbon::parse($date)->toDateString();

        return $query->where(function ($q) use ($workDate) {
            $q->whereDate('work_date', $workDate)
                ->orWhere(function ($fallback) use ($workDate) {
                    $fallback->whereNull('work_date')
                        ->whereDate('checkin_at', $workDate);
                });
        });
    }

    public function scopeForWorkDateBetween($query, $start, $end)
    {
        $startDate = Carbon::parse($start)->toDateString();
        $endDate = Carbon::parse($end)->toDateString();

        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('work_date', [$startDate, $endDate])
                ->orWhere(function ($fallback) use ($startDate, $endDate) {
                    $fallback->whereNull('work_date')
                        ->whereDate('checkin_at', '>=', $startDate)
                        ->whereDate('checkin_at', '<=', $endDate);
                });
        });
    }
}
