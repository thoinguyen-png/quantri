<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceAdjustmentLog extends Model
{
    protected $fillable = [
        'attendance_id',
        'user_id',
        'work_date',
        'action',
        'old_values',
        'new_values',
        'changed_by',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
