<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    public function test_user_role_helper_methods(): void
    {
        $superAdmin = new User(['role' => User::ROLE_SUPER_ADMIN]);
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->isAdmin()); // Super Admin is also an admin
        $this->assertFalse($superAdmin->isTeacher());
        $this->assertFalse($superAdmin->isStudentAffairs());
        $this->assertFalse($superAdmin->isParent());

        $admin = new User(['role' => User::ROLE_ADMIN]);
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isTeacher());

        $teacher = new User(['role' => User::ROLE_TEACHER]);
        $this->assertFalse($teacher->isSuperAdmin());
        $this->assertFalse($teacher->isAdmin());
        $this->assertTrue($teacher->isTeacher());

        $sa = new User(['role' => User::ROLE_STUDENT_AFFAIRS]);
        $this->assertTrue($sa->isStudentAffairs());

        $parent = new User(['role' => User::ROLE_PARENT]);
        $this->assertTrue($parent->isParent());
    }

    public function test_user_reopen_authorization_methods(): void
    {
        $superAdmin = new User(['role' => User::ROLE_SUPER_ADMIN]);
        $this->assertTrue($superAdmin->canApproveSessionReopen());
        $this->assertTrue($superAdmin->canRequestSessionReopen());

        $admin = new User(['role' => User::ROLE_ADMIN]);
        $this->assertTrue($admin->canApproveSessionReopen());
        $this->assertTrue($admin->canRequestSessionReopen());

        $sa = new User(['role' => User::ROLE_STUDENT_AFFAIRS]);
        $this->assertTrue($sa->canApproveSessionReopen());
        $this->assertTrue($sa->canRequestSessionReopen());

        $teacher = new User(['role' => User::ROLE_TEACHER]);
        $this->assertFalse($teacher->canApproveSessionReopen());
        $this->assertTrue($teacher->canRequestSessionReopen());

        $parent = new User(['role' => User::ROLE_PARENT]);
        $this->assertFalse($parent->canApproveSessionReopen());
        $this->assertFalse($parent->canRequestSessionReopen());
    }

    public function test_has_role_logic(): void
    {
        $superAdmin = new User(['role' => User::ROLE_SUPER_ADMIN]);
        // Super admin satisfies any requested role
        $this->assertTrue($superAdmin->hasRole(User::ROLE_TEACHER));
        $this->assertTrue($superAdmin->hasRole(User::ROLE_ADMIN));
        $this->assertTrue($superAdmin->hasRole('any_custom_role'));

        $teacher = new User(['role' => User::ROLE_TEACHER]);
        $this->assertTrue($teacher->hasRole(User::ROLE_TEACHER));
        $this->assertFalse($teacher->hasRole(User::ROLE_ADMIN));
        $this->assertTrue($teacher->hasRole(User::ROLE_ADMIN, User::ROLE_TEACHER));
    }
}
