<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'telegram_chat_id', 'password', 'role', 'role_id', 'campus_id', 'is_active', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STUDENT_AFFAIRS = 'student_affairs';

    public const ROLE_PARENT = 'parent';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_TEACHER,
        self::ROLE_STUDENT_AFFAIRS,
        self::ROLE_PARENT,
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Keep role string and role_id in sync
            if ($user->role_id && empty($user->role)) {
                $role = Role::find($user->role_id);
                if ($role) {
                    $user->role = $role->slug;
                }
            } elseif (! empty($user->role) && empty($user->role_id)) {
                $role = Role::where('slug', $user->role)->first();
                if ($role) {
                    $user->role_id = $role->id;
                }
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->isSuperAdmin();
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isStudentAffairs(): bool
    {
        return $this->role === self::ROLE_STUDENT_AFFAIRS;
    }

    public function isParent(): bool
    {
        return $this->role === self::ROLE_PARENT;
    }

    public function canApproveSessionReopen(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin() || $this->isStudentAffairs();
    }

    public function canRequestSessionReopen(): bool
    {
        return $this->isTeacher() || $this->canApproveSessionReopen();
    }

    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function hasRole(string ...$roles): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($this->role, $roles, true);
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->role_id && $this->role) {
            $this->loadMissing('roleRecord.systemPermissions');
        }

        return (bool) $this->roleRecord?->hasPermission($permissionSlug);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class, 'teacher_id');
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'teacher_id');
    }

    public function substitutionsAsSubstitute(): HasMany
    {
        return $this->hasMany(ScheduleSubstitution::class, 'substitute_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'parent_user_id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(Campus::class, 'campus_user');
    }

    /**
     * Get all campus IDs this user is authorized for.
     */
    public function assignedCampusIds(): array
    {
        if ($this->relationLoaded('campuses')) {
            $ids = $this->campuses->pluck('id')->all();
        } else {
            $ids = $this->campuses()->pluck('campuses.id')->all();
        }

        if ($this->isSuperAdmin()) {
            if (! empty($ids)) {
                return array_values(array_unique(array_map('intval', $ids)));
            }

            return Campus::where('is_active', true)->pluck('id')->all();
        }

        if (empty($ids) && $this->campus_id) {
            $ids = [(int) $this->campus_id];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Get Collection of assigned Campus models for this user.
     *
     * @return Collection<int, Campus>
     */
    public function assignedCampuses()
    {
        $assignedIds = $this->assignedCampusIds();

        if (empty($assignedIds)) {
            return collect();
        }

        return Campus::whereIn('id', $assignedIds)->where('is_active', true)->orderBy('id')->get();
    }

    /**
     * Check whether user is assigned to / has access to a specific campus.
     */
    public function hasCampusAccess(int|string|null $campusId): bool
    {
        if ($campusId === null) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $assignedIds = $this->assignedCampusIds();
        if (empty($assignedIds)) {
            return false;
        }

        return in_array((int) $campusId, $assignedIds, true);
    }

    public function activeCampusId(): ?int
    {
        $sessionCampusId = session('active_campus_id');

        if ($this->isSuperAdmin()) {
            return $sessionCampusId ? (int) $sessionCampusId : null;
        }

        $assignedIds = $this->assignedCampusIds();

        if (! empty($assignedIds)) {
            if ($sessionCampusId && in_array((int) $sessionCampusId, $assignedIds, true)) {
                return (int) $sessionCampusId;
            }

            if (count($assignedIds) === 1) {
                return $assignedIds[0];
            }

            return $sessionCampusId ? (int) $sessionCampusId : null;
        }

        return $this->campus_id ? (int) $this->campus_id : null;
    }
}
