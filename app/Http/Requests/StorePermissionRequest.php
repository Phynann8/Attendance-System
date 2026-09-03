<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isAdmin() || $this->user()->isParent());
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'attendance_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
            'requested_by' => ['required', 'string', 'max:191'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendance_date.after_or_equal' => 'Permission requests can only be created for today or a future class day.',
        ];
    }
}