<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRatingRewardCounter extends Model
{
    protected $fillable = [
        'employee_id',
        'business_date',
        'rewarded_good_count',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'rewarded_good_count' => 'integer',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
