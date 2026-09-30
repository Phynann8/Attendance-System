<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'day_of_week',
        'period_number',
        'subject',
        'start_time',
        'end_time',
        'teacher_id',
        'is_primary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'period_number' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(ScheduleSubstitution::class, 'class_schedule_id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'class_schedule_id');
    }

    public function dayName(): string
    {
        return match ($this->day_of_week) {
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
            default => __('Day :d', ['d' => $this->day_of_week]),
        };
    }

    public function timeRange(): string
    {
        if ($this->start_time && $this->end_time) {
            return "{$this->start_time} - {$this->end_time}";
        }

        return $this->start_time ?? '';
    }

    public function label(): string
    {
        $time = $this->timeRange() ? " ({$this->timeRange()})" : '';

        return __('Period :p: :subject:time', [
            'p' => $this->period_number,
            'subject' => $this->subject,
            'time' => $time,
        ]);
    }

    public function getActiveTeacherForDate(Carbon|string $date): User
    {
        $dateString = is_string($date) ? $date : $date->toDateString();

        $sub = $this->substitutions()
            ->whereDate('session_date', $dateString)
            ->with('substituteTeacher')
            ->first();

        if ($sub && $sub->substituteTeacher) {
            return $sub->substituteTeacher;
        }

        return $this->teacher;
    }

    public function getSubstitutionForDate(Carbon|string $date): ?ScheduleSubstitution
    {
        $dateString = is_string($date) ? $date : $date->toDateString();

        return $this->substitutions()
            ->whereDate('session_date', $dateString)
            ->first();
    }
}
