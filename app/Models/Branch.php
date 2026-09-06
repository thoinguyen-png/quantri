<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'address',
        'hotline',
        'latitude',
        'longitude',
        'gps_radius',
        'is_active',
        'staff_card_logo_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_radius' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (Branch $branch) {
            if ($branch->wasChanged('is_active') && ! $branch->is_active) {
                $branch->deactivateStaff();
            }
        });
    }

    public function deactivateStaff(?int $actorId = null): int
    {
        $actorId = $actorId ?? (auth()->check() ? auth()->id() : null);
        $today = today()->toDateString();

        return $this->users()
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'da_nghi');
            })
            ->update([
                'status' => 'da_nghi',
                'employment_status' => 'resigned',
                'is_active' => false,
                'resigned_at' => $today,
                'resigned_by' => $actorId,
            ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function shifts()
    {
        return $this->belongsToMany(Shift::class)->withTimestamps();
    }

    public function customerRatings()
    {
        return $this->hasMany(CustomerRating::class);
    }

    public function customerRatingRewards()
    {
        return $this->hasMany(CustomerRatingReward::class);
    }

    public function getStaffCardLogoUrlAttribute(): string
    {
        $path = trim((string) $this->staff_card_logo_path);

        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return asset('icons/logoSantori.png');
    }

    public function hasStaffCardLogo(): bool
    {
        $path = trim((string) $this->staff_card_logo_path);

        return $path !== '' && Storage::disk('public')->exists($path);
    }
}
