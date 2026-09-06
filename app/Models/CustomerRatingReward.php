<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRatingReward extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_RISK_REVIEW = 'risk_review';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'customer_rating_id',
        'employee_id',
        'branch_id',
        'attendance_id',
        'business_date',
        'amount',
        'status',
        'reason_code',
        'settings_snapshot',
        'reviewed_by',
        'reviewed_at',
        'paid_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'amount' => 'integer',
            'settings_snapshot' => 'array',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function customerRating()
    {
        return $this->belongsTo(CustomerRating::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
