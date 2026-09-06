<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRating extends Model
{
    public const RATING_BAD = 'bad';
    public const RATING_AVERAGE = 'average';
    public const RATING_GOOD = 'good';
    public const RISK_CLEAR = 'clear';
    public const RISK_PENDING_REVIEW = 'pending_review';
    public const RISK_APPROVED_MANUAL = 'approved_manual';
    public const RISK_REJECTED_MANUAL = 'rejected_manual';

    protected $fillable = [
        'employee_id',
        'branch_id',
        'shift_id',
        'shift_assignment_id',
        'attendance_id',
        'attendance_segment_id',
        'rating_qr_token_id',
        'work_date',
        'business_date',
        'rating',
        'comment',
        'guest_browser_hash',
        'ip_hash',
        'user_agent_hash',
        'settings_snapshot',
        'risk_status',
        'risk_reasons',
        'risk_checked_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'business_date' => 'date',
            'settings_snapshot' => 'array',
            'risk_reasons' => 'array',
            'risk_checked_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftAssignment()
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function attendanceSegment()
    {
        return $this->belongsTo(AttendanceSegment::class);
    }

    public function ratingQrToken()
    {
        return $this->belongsTo(RatingQrToken::class);
    }

    public function reward()
    {
        return $this->hasOne(CustomerRatingReward::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
