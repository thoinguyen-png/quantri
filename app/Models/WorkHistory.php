<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkHistory extends Model
{
    protected $fillable = [
        'user_id',
        'old_branch_id',
        'new_branch_id',
        'old_department_id',
        'new_department_id',
        'old_role',
        'new_role',
        'old_status',
        'new_status',
        'effective_date',
        'changed_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function oldBranch()
    {
        return $this->belongsTo(Branch::class, 'old_branch_id');
    }

    public function newBranch()
    {
        return $this->belongsTo(Branch::class, 'new_branch_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
