<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRoom extends Model
{
    protected $table = 'classes';

    protected $fillable = ['campus_id', 'name', 'grade', 'teacher_id', 'psis_group_id'];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function scopeForCampus($query, ?int $campusId)
    {
        if ($campusId !== null) {
            return $query->where('campus_id', $campusId);
        }

        return $query;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function activeStudents(): HasMany
    {
        return $this->students()->where('is_active', true)->orderBy('name');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'class_id');
    }
}
