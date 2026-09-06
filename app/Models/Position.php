<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_CASHIER = 'cashier';
    public const ROLE_STAFF = 'staff';

    public const SYSTEM_ROLES = [
        self::ROLE_ADMIN => 'Ban Quản trị (Admin)',
        self::ROLE_MANAGER => 'Quản lý chi nhánh (Manager)',
        self::ROLE_CASHIER => 'Thu ngân (Cashier)',
        self::ROLE_STAFF => 'Nhân viên vận hành (Staff)',
    ];

    protected $fillable = [
        'name',
        'name_en',
        'code',
        'system_role',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getSystemRoleLabelAttribute(): string
    {
        return self::SYSTEM_ROLES[$this->system_role] ?? $this->system_role;
    }
}
