<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    public const TTL_DASHBOARD = 60;        // 1 minute

    public const TTL_ROSTER = 3600;          // 1 hour

    public const TTL_PERMISSIONS = 86400;    // 24 hours

    public const TTL_SETTINGS = 86400;       // 24 hours

    /**
     * Cache and retrieve dashboard statistics.
     */
    public static function rememberDashboardStats(string $role, Closure $callback): mixed
    {
        $key = "stats:dashboard:{$role}";

        return Cache::remember($key, self::TTL_DASHBOARD, $callback);
    }

    /**
     * Cache and retrieve role permissions array.
     */
    public static function rememberRolePermissions(int $roleId, Closure $callback): mixed
    {
        $key = "role:{$roleId}:permissions";

        return Cache::remember($key, self::TTL_PERMISSIONS, $callback);
    }

    /**
     * Cache and retrieve active students for a class.
     */
    public static function rememberClassRoster(int $classId, Closure $callback): mixed
    {
        $key = "class:{$classId}:roster";

        return Cache::remember($key, self::TTL_ROSTER, $callback);
    }

    /**
     * Cache and retrieve all school settings.
     */
    public static function rememberSchoolSettings(Closure $callback): mixed
    {
        return Cache::remember('school:settings:all', self::TTL_SETTINGS, $callback);
    }

    /**
     * Invalidate dashboard statistics across all roles when attendance or permissions change.
     */
    public static function invalidateDashboardStats(): void
    {
        // Forget common role dashboard keys
        Cache::forget('stats:dashboard:admin');
        Cache::forget('stats:dashboard:super_admin');
        Cache::forget('stats:dashboard:student_affairs');

        // Flush tagged or wildcards where supported
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['dashboard'])->flush();
            }
        } catch (\Throwable) {
            // Ignore if driver does not support tags
        }
    }

    /**
     * Invalidate role permissions cache.
     */
    public static function invalidateRolePermissions(int $roleId): void
    {
        Cache::forget("role:{$roleId}:permissions");
    }

    /**
     * Invalidate class roster cache.
     */
    public static function invalidateClassRoster(int $classId): void
    {
        Cache::forget("class:{$classId}:roster");
    }

    /**
     * Invalidate school settings cache.
     */
    public static function invalidateSchoolSettings(): void
    {
        Cache::forget('school:settings:all');
    }

    /**
     * Invalidate all affected attendance caches upon a state change.
     */
    public static function invalidateAttendanceCache(?int $classId = null, ?string $date = null): void
    {
        self::invalidateDashboardStats();

        if ($classId) {
            Cache::forget("class:{$classId}:active_session:{$date}");
        }
    }

    /**
     * Acquire an atomic lock for coordinating concurrent actions.
     */
    public static function lock(string $name, int $seconds = 10): Lock
    {
        return Cache::lock("lock:{$name}", $seconds);
    }
}
