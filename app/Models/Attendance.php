<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
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
        'work_units',
        'penalty_workday',
        'status',
        'face_verification_mode',
        'is_locked',
        'locked_at',
        'locked_by',
        'manual_updated_by',
        'manual_updated_at',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'checkin_at' => 'datetime',
        'checkout_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'manual_updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
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

    public function segments()
    {
        return $this->hasMany(AttendanceSegment::class)->orderBy('segment_order');
    }

    public function customerRatings()
    {
        return $this->hasMany(CustomerRating::class);
    }

    public function customerRatingRewards()
    {
        return $this->hasMany(CustomerRatingReward::class);
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

    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhere(function ($fallback) use ($branchId) {
                    $fallback->whereNull('branch_id')
                        ->whereHas('user', fn ($uq) => $uq->where('branch_id', $branchId));
                });
        });
    }
}
