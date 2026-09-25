<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\SystemPermission;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedisCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_are_cached_and_invalidated(): void
    {
        $callCount = 0;
        $fetchStats = function () use (&$callCount) {
            $callCount++;

            return ['active_sessions' => 5];
        };

        // First call populates cache
        $first = CacheService::rememberDashboardStats('admin', $fetchStats);
        $this->assertEquals(1, $callCount);
        $this->assertEquals(5, $first['active_sessions']);

        // Second call retrieves from cache
        $second = CacheService::rememberDashboardStats('admin', $fetchStats);
        $this->assertEquals(1, $callCount);
        $this->assertEquals(5, $second['active_sessions']);

        // Invalidation clears the key
        CacheService::invalidateDashboardStats();

        // Third call re-executes callback
        $third = CacheService::rememberDashboardStats('admin', $fetchStats);
        $this->assertEquals(2, $callCount);
        $this->assertEquals(5, $third['active_sessions']);
    }

    public function test_role_permissions_are_cached_and_invalidated_on_update(): void
    {
        $role = Role::create([
            'name' => 'Counselor',
            'slug' => 'counselor',
            'is_system' => false,
        ]);

        $permission = SystemPermission::create([
            'module' => 'counseling',
            'slug' => 'module.counseling',
            'name' => 'View Counseling Notes',
        ]);

        $role->systemPermissions()->attach($permission->id);

        // Initial fetch caches
        $this->assertTrue($role->hasPermission('module.counseling'));

        // Verify key exists in cache
        $this->assertTrue(Cache::has("role:{$role->id}:permissions"));

        // Invalidate via syncPermissions
        $role->syncPermissions([]);
        $this->assertFalse(Cache::has("role:{$role->id}:permissions"));
        $this->assertFalse($role->hasPermission('module.counseling'));
    }

    public function test_school_settings_are_cached_and_cleared_on_set(): void
    {
        SchoolSetting::set('school_name', 'St. Jude Academy');

        // Access via get() to cache
        $name = SchoolSetting::get('school_name');
        $this->assertEquals('St. Jude Academy', $name);
        $this->assertTrue(Cache::has('school:settings:all'));

        // Setting a new value clears cache
        SchoolSetting::set('school_name', 'St. Jude International Academy');
        $this->assertEquals('St. Jude International Academy', SchoolSetting::get('school_name'));
    }

    public function test_cache_service_atomic_lock(): void
    {
        $lock = CacheService::lock('attendance_session_1', 5);

        $acquired = $lock->get();
        $this->assertTrue($acquired, 'First lock acquisition must succeed.');

        $secondLock = CacheService::lock('attendance_session_1', 5);
        $secondAcquired = $secondLock->get();
        $this->assertFalse($secondAcquired, 'Concurrent lock acquisition on same key must fail.');

        $lock->release();

        $thirdAcquired = $secondLock->get();
        $this->assertTrue($thirdAcquired, 'Lock acquisition must succeed after release.');
        $secondLock->release();
    }
}
