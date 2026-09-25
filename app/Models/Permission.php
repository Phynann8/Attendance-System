<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const REQUESTED_BY_PARENT = 'parent';

    public const REQUESTED_BY_ADMIN = 'admin';

    public const CATEGORY_MEDICAL = 'medical';

    public const CATEGORY_FAMILY_EMERGENCY = 'family_emergency';

    public const CATEGORY_OFFICIAL_ACTIVITY = 'official_activity';

    public const CATEGORY_BEREAVEMENT = 'bereavement';

    public const CATEGORY_UNEXCUSED = 'unexcused';

    public const CATEGORY_OTHER = 'other';

    public static function categories(): array
    {
        return [
            self::CATEGORY_MEDICAL => __('Medical / Illness'),
            self::CATEGORY_FAMILY_EMERGENCY => __('Family Emergency'),
            self::CATEGORY_OFFICIAL_ACTIVITY => __('Official School Representation'),
            self::CATEGORY_BEREAVEMENT => __('Bereavement'),
            self::CATEGORY_UNEXCUSED => __('Unexcused / Personal'),
            self::CATEGORY_OTHER => __('Other'),
        ];
    }

    protected $fillable = [
        'student_id',
        'class_id',
        'attendance_date',
        'requested_by',
        'requested_by_type',
        'reason',
        'category',
        'detail_description',
        'evidence_path',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function scopeForCampus($query, ?int $campusId)
    {
        if ($campusId !== null) {
            return $query->whereHas('student', fn ($q) => $q->where('campus_id', $campusId));
        }

        return $query;
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
