<?php

namespace App\Models;

use App\Models\Legacy\LegacySchoolCalendar;
use Illuminate\Database\Eloquent\Model;

class LegacySchoolCalendarEvent extends Model
{
    protected $table = 'school_calendar_events';
    protected $fillable = ['school_calendar_id', 'date', 'type', 'description'];

    public function calendar()
    {
        return $this->belongsTo(LegacySchoolCalendar::class, 'school_calendar_id');
    }
}
