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
        'position_id',
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
        'employee_code',
        'face_image_path',
        'is_active',
        'face_descriptor',
        'face_verification_mode',
        'rating_qr_enabled',
        'public_rating_code',
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->position_id && ($user->isDirty('position_id') || empty($user->role))) {
                $position = Position::find($user->position_id);
                if ($position && !empty($position->system_role)) {
                    $user->role = $position->system_role;
                }
            }
        });
    }

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
            'rating_qr_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
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

    public function ratingQrTokens()
    {
        return $this->hasMany(RatingQrToken::class);
    }

    public function activeRatingQrToken()
    {
        return $this->hasOne(RatingQrToken::class)
            ->where('enabled', true)
            ->whereNull('revoked_at')
            ->latestOfMany();
    }

    public function customerRatings()
    {
        return $this->hasMany(CustomerRating::class, 'employee_id');
    }

    public function customerRatingRewards()
    {
        return $this->hasMany(CustomerRatingReward::class, 'employee_id');
    }

    public function customerRatingRewardCounters()
    {
        return $this->hasMany(CustomerRatingRewardCounter::class, 'employee_id');
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

    public function getEmployeeCodeAttribute($value): string
    {
        $code = trim((string) $value);

        return $code !== ''
            ? str_pad($code, 4, '0', STR_PAD_LEFT)
            : '----';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $path = trim((string) $this->face_image_path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:image')) {
            return $path;
        }

        $relativePath = ltrim($path, '/');
        $localCandidates = [];

        if (str_starts_with($relativePath, 'storage/')) {
            $localCandidates[] = public_path($relativePath);
            $relativePath = substr($relativePath, strlen('storage/'));
        }

        $localCandidates[] = storage_path('app/public/' . $relativePath);
        $localCandidates[] = public_path($relativePath);

        foreach ($localCandidates as $localPath) {
            if (is_file($localPath)) {
                $mime = mime_content_type($localPath) ?: 'image/jpeg';
                $contents = file_get_contents($localPath);

                if ($contents !== false) {
                    return 'data:' . $mime . ';base64,' . base64_encode($contents);
                }
            }
        }

        if (str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return is_file(storage_path('app/public/' . ltrim($path, '/')))
            ? asset('storage/' . ltrim($path, '/'))
            : null;
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
