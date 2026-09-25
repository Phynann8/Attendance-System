<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SystemPermission;
use App\Services\CacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')
            ->with('systemPermissions')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('super_admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissionsByModule = SystemPermission::orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');

        return view('super_admin.roles.create', compact('permissionsByModule'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:system_permissions,id'],
        ]);

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'], '_')
            : Str::slug($validated['name'], '_');

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->systemPermissions()->sync($validated['permissions']);
        }

        return redirect()->route('super-admin.roles.index')->with('success', "Custom role '{$role->name}' created successfully.");
    }

    public function edit(Role $role): View
    {
        $role->load('systemPermissions');

        $permissionsByModule = SystemPermission::orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');

        return view('super_admin.roles.edit', compact('role', 'permissionsByModule'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:system_permissions,id'],
        ];

        if (! $role->is_system) {
            $rules['slug'] = ['nullable', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($role->id)];
        }

        $validated = $request->validate($rules);

        $role->name = $validated['name'];
        $role->description = $validated['description'] ?? null;

        if (! $role->is_system && ! empty($validated['slug'])) {
            $role->slug = Str::slug($validated['slug'], '_');
        }

        $role->save();

        // Sync permissions
        $role->systemPermissions()->sync($validated['permissions'] ?? []);
        CacheService::invalidateRolePermissions($role->id);

        return redirect()->route('super-admin.roles.index')->with('success', "Role '{$role->name}' updated successfully.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', "Cannot delete role '{$role->name}' because it is assigned to users. Reassign users first.");
        }

        $role->systemPermissions()->detach();
        $role->delete();

        return redirect()->route('super-admin.roles.index')->with('success', "Custom role '{$role->name}' deleted successfully.");
    }
}
