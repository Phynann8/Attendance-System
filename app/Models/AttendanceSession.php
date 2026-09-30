<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CLOSED = 'closed';

    public const REOPEN_NONE = 'none';

    public const REOPEN_PENDING = 'pending';

    public const REOPEN_APPROVED = 'approved';

    public const REOPEN_REJECTED = 'rejected';

    protected $fillable = [
        'class_id',
        'class_schedule_id',
        'teacher_id',
        'session_date',
        'opened_at',
        'submitted_at',
        'status',
        'reopen_status',
        'reopen_reason',
        'reopen_requested_by',
        'reopen_requested_at',
        'reopen_decided_by',
        'reopen_decided_at',
        'reopen_decision_note',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'opened_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reopen_requested_at' => 'datetime',
            'reopen_decided_at' => 'datetime',
        ];
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'class_schedule_id');
    }

    public function isPrimary(): bool
    {
        return $this->schedule ? $this->schedule->is_primary : true;
    }

    public function periodLabel(): string
    {
        if ($this->schedule) {
            return $this->schedule->label();
        }

        return __('Homeroom Daily Session');
    }

    public function scopeForCampus($query, ?int $campusId)
    {
        if ($campusId !== null) {
            return $query->whereHas('classRoom', fn ($q) => $q->where('campus_id', $campusId));
        }

        return $query;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'attendance_session_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED || $this->status === self::STATUS_CLOSED;
    }

    public function reopenRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopen_requested_by');
    }

    public function reopenDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopen_decided_by');
    }

    public function isReopenPending(): bool
    {
        return $this->reopen_status === self::REOPEN_PENDING;
    }
}
