<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvigilationDuty extends Model
{
    protected $fillable = [
        'invigilator_id',
        'date',
        'start_time',
        'end_time',
        'course_codes',
        'room',
        'lecturer_name',
        'student_count',
        'student_count_note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function invigilator(): BelongsTo
    {
        return $this->belongsTo(Invigilator::class);
    }
}