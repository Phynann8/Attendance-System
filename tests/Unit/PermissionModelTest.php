<?php

namespace Tests\Unit;

use App\Models\Permission;
use Tests\TestCase;

class PermissionModelTest extends TestCase
{
    public function test_permission_categories_returns_complete_taxonomy(): void
    {
        $categories = Permission::categories();

        $this->assertIsArray($categories);
        $this->assertArrayHasKey(Permission::CATEGORY_MEDICAL, $categories);
        $this->assertArrayHasKey(Permission::CATEGORY_FAMILY_EMERGENCY, $categories);
        $this->assertArrayHasKey(Permission::CATEGORY_OFFICIAL_ACTIVITY, $categories);
        $this->assertArrayHasKey(Permission::CATEGORY_BEREAVEMENT, $categories);
        $this->assertArrayHasKey(Permission::CATEGORY_UNEXCUSED, $categories);
        $this->assertArrayHasKey(Permission::CATEGORY_OTHER, $categories);

        // Every category value must be non-empty string
        foreach ($categories as $key => $label) {
            $this->assertNotEmpty($key);
            $this->assertNotEmpty($label);
        }
    }

    public function test_permission_constants_have_expected_values(): void
    {
        $this->assertSame('pending', Permission::STATUS_PENDING);
        $this->assertSame('approved', Permission::STATUS_APPROVED);
        $this->assertSame('rejected', Permission::STATUS_REJECTED);
        $this->assertSame('parent', Permission::REQUESTED_BY_PARENT);
        $this->assertSame('admin', Permission::REQUESTED_BY_ADMIN);
    }
}
