<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'zalo_phone',
        'citizen_id',
        'start_work_date',
        'role',
        'status',
        'employment_status',
        'hired_at',
        'hired_by',
        'official_at',
        'official_by',
        'resigned_at',
        'resigned_by',
        'status_changed_by',
        'status_changed_at',
        'branch_id',
        'face_image_path',
        'is_active',
        'face_descriptor',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'start_work_date' => 'date',
            'hired_at' => 'date',
            'official_at' => 'date',
            'resigned_at' => 'date',
            'status_changed_at' => 'datetime',
        ];
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function shiftAssignments()
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceSupplementRequests()
    {
        return $this->hasMany(AttendanceSupplementRequest::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function workHistories()
    {
        return $this->hasMany(WorkHistory::class);
    }

    public function statusChangedBy()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function hiredBy()
    {
        return $this->belongsTo(User::class, 'hired_by');
    }

    public function officialBy()
    {
        return $this->belongsTo(User::class, 'official_by');
    }

    public function resignedBy()
    {
        return $this->belongsTo(User::class, 'resigned_by');
    }

    public function getEmployeeCodeAttribute(): string
    {
        return str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isManager()
    {
        return $this->role === 'manager';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isCashier()
    {
        return $this->role === 'cashier';
    }
}
