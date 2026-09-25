<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'campus_id',
        'student_code',
        'name',
        'khmer_name',
        'gender',
        'dob',
        'class_id',
        'parent_name',
        'parent_phone',
        'parent_email',
        'parent_user_id',
        'is_active',
        'psis_student_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'dob' => 'date',
        ];
    }

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

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
