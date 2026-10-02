<?php

namespace Tests\Unit;

use App\Models\Student;
use PHPUnit\Framework\TestCase;

class StudentModelUnitTest extends TestCase
{
    public function test_student_model_casts_and_fillables(): void
    {
        $student = new Student([
            'name' => 'Sok Dara',
            'khmer_name' => 'សុខ តារា',
            'student_code' => 'STU-001',
            'is_active' => 1,
            'gender' => 'M',
        ]);

        $this->assertSame('Sok Dara', $student->name);
        $this->assertSame('សុខ តារា', $student->khmer_name);
        $this->assertSame('STU-001', $student->student_code);
        $this->assertTrue($student->is_active);
        $this->assertSame('M', $student->gender);
    }

    public function test_student_fillable_attributes(): void
    {
        $student = new Student;
        $fillable = $student->getFillable();

        $this->assertContains('campus_id', $fillable);
        $this->assertContains('student_code', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('khmer_name', $fillable);
        $this->assertContains('gender', $fillable);
        $this->assertContains('dob', $fillable);
        $this->assertContains('class_id', $fillable);
        $this->assertContains('parent_name', $fillable);
        $this->assertContains('parent_phone', $fillable);
        $this->assertContains('parent_email', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertContains('psis_student_id', $fillable);
    }
}
