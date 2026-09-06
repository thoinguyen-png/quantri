<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const RATING_REQUIRE_ACTIVE_ATTENDANCE = 'rating_require_active_attendance';

    public const CUSTOMER_RATING_DEFAULTS = [
        'good_reward_amount' => 0,
        'max_rewarded_good_per_employee_per_business_date' => 3,
        'max_distinct_employees_per_guest_browser_per_branch_per_business_date' => 3,
        'rating_risk_ip_employee_window_minutes' => 15,
        'rating_risk_ip_employee_repeat_window_minutes' => 60,
        'rating_risk_ip_employee_repeat_threshold' => 3,
        'rating_risk_many_employees_ip_window_minutes' => 30,
        'rating_risk_many_employees_ip_threshold' => 5,
        'rating_risk_burst_good_window_minutes' => 15,
        'rating_risk_burst_good_threshold' => 5,
    ];

    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    public static function getInt(string $key, int $default): int
    {
        $value = static::query()->where('key', $key)->value('value');

        if (!is_numeric($value)) {
            return $default;
        }

        return max(1, (int) $value);
    }

    public static function getValue(string $key, string|int|null $default = null): string|int|null
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public static function getBool(string $key, bool $default): bool
    {
        $value = static::query()->where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function setValue(string $key, string|int|null $value, ?int $updatedBy = null): void
    {
        $attributes = ['value' => $value];

        if ($updatedBy !== null) {
            $attributes['updated_by'] = $updatedBy;
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            $attributes
        );
    }

    public static function getCustomerRatingInt(string $key): int
    {
        $default = static::CUSTOMER_RATING_DEFAULTS[$key] ?? 0;
        $value = static::query()->where('key', $key)->value('value');

        if (!is_numeric($value)) {
            return $default;
        }

        return max(0, (int) $value);
    }

    public static function customerRatingSettingsSnapshot(): array
    {
        $settings = collect(static::CUSTOMER_RATING_DEFAULTS)
            ->mapWithKeys(fn (int $default, string $key) => [$key => static::getCustomerRatingInt($key)])
            ->all();

        return $settings + [
            static::RATING_REQUIRE_ACTIVE_ATTENDANCE => static::ratingRequiresActiveAttendance(),
        ];
    }

    public static function ratingRequiresActiveAttendance(): bool
    {
        return static::getBool(static::RATING_REQUIRE_ACTIVE_ATTENDANCE, true);
    }
}
