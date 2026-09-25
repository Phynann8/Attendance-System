<?php

namespace App\Models;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function systemPermissions(): BelongsToMany
    {
        return $this->belongsToMany(
            SystemPermission::class,
            'role_system_permission',
            'role_id',
            'system_permission_id'
        )->withTimestamps();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->slug === User::ROLE_SUPER_ADMIN) {
            return true;
        }

        $permissions = CacheService::rememberRolePermissions($this->id, function () {
            return $this->systemPermissions()->pluck('slug')->toArray();
        });

        return in_array($slug, $permissions, true);
    }

    public function syncPermissions(array $permissionIds): void
    {
        $this->systemPermissions()->sync($permissionIds);
        CacheService::invalidateRolePermissions($this->id);
    }
}
