<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_PERMISSION = 'permission';

    public const CASE_PENDING = 'pending';

    public const CASE_CLOSED = 'closed';

    public const CASE_ESCALATED = 'escalated';

    public const FINAL_PRESENT = 'present';

    public const FINAL_LATE = 'late';

    public const FINAL_EXCUSED = 'excused';

    public const FINAL_ABSENT_WITHOUT_PERMISSION = 'absent_without_permission';

    protected $fillable = [
        'attendance_session_id',
        'student_id',
        'status',
        'marked_by',
        'marked_at',
        'is_locked',
        'locked_by',
        'locked_at',
        'lock_reason',
        'permission_id',
        'arrived_at',
        'minutes_late',
        'case_status',
        'case_closed_by',
        'case_closed_at',
        'final_status',
        'finalized_by',
        'finalized_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'marked_at' => 'datetime',
            'locked_at' => 'datetime',
            'arrived_at' => 'datetime',
            'case_closed_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function scopeForCampus($query, ?int $campusId)
    {
        if ($campusId !== null) {
            return $query->whereHas('student', fn ($q) => $q->where('campus_id', $campusId));
        }

        return $query;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function caseCloser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'case_closed_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'attendance_id');
    }

    public function isPermissionLocked(): bool
    {
        return $this->is_locked && $this->status === self::STATUS_PERMISSION;
    }
}
