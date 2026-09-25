<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->with(['roleRecord', 'campus']);

        // Status filter: active, inactive, trashed, or all
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'trashed') {
            $query->onlyTrashed();
        } else {
            $query->withTrashed();
        }

        // Role filter
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }

        // Campus filter
        if ($request->filled('campus_id')) {
            $query->where('campus_id', $request->input('campus_id'));
        }

        // Search query
        if ($request->filled('q')) {
            $search = '%'.trim($request->input('q')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();
        $roles = Role::orderBy('name')->get();
        $campuses = Campus::where('is_active', true)->orderBy('id')->get();

        return view('super_admin.users.index', compact('users', 'roles', 'campuses'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();
        $campuses = Campus::where('is_active', true)->orderBy('id')->get();

        return view('super_admin.users.create', compact('roles', 'campuses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:6'],
            'role_id' => ['required', 'exists:roles,id'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $role->id,
            'role' => $role->slug,
            'campus_id' => $validated['campus_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('super-admin.users.index')->with('success', "User '{$validated['name']}' created successfully.");
    }

    public function edit(int $id): View
    {
        $user = User::withTrashed()->findOrFail($id);
        $roles = Role::orderBy('name')->get();
        $campuses = Campus::where('is_active', true)->orderBy('id')->get();

        return view('super_admin.users.edit', compact('user', 'roles', 'campuses'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'role_id' => ['required', 'exists:roles,id'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role_id = $role->id;
        $user->role = $role->slug;
        $user->campus_id = $validated['campus_id'] ?? null;
        $user->is_active = $request->boolean('is_active', true);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('super-admin.users.index')->with('success', "User '{$user->name}' updated successfully.");
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own active status.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $statusText = $user->is_active ? 'activated' : 'inactivated';

        return back()->with('success', "User '{$user->name}' has been {$statusText}.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->is_active = false;
        $user->save();
        $user->delete();

        return redirect()->route('super-admin.users.index')->with('success', "User '{$user->name}' inactivated and soft-deleted.");
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();
        $user->is_active = true;
        $user->save();

        return redirect()->route('super-admin.users.index')->with('success', "User '{$user->name}' restored and activated.");
    }
}
