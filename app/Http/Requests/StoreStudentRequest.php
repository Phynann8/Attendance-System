<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'class_id' => ['required', 'exists:classes,id'],
            'parent_name' => ['nullable', 'string', 'max:191'],
            'parent_phone' => ['nullable', 'string', 'max:50'],
            'parent_email' => ['nullable', 'email', 'max:191'],
            'parent_user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}