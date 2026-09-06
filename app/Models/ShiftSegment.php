<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftSegment extends Model
{
    protected $fillable = ['shift_id', 'segment_order', 'start_at', 'end_at'];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
