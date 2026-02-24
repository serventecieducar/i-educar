<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LegacySchoolCalendar extends Model
{
    protected $table = 'school_calendars';
    protected $fillable = ['school_id', 'year', 'status'];

    public function events()
    {
        return $this->hasMany(LegacySchoolCalendarEvent::class, 'school_calendar_id');
    }
}


