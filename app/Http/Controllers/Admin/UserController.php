<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function create(): View
    {
        Gate::authorize('manage-system');

        return view('admin.user-form', ['user' => new User]);
    }

    public function store(Request $request, AdminAuditService $audit): RedirectResponse
    {
        Gate::authorize('manage-system');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);
        DB::transaction(function () use ($data, $audit): void {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
            $user->role = $data['role'];
            $user->save();
            $user->profile()->create(['full_name' => $user->name]);
            $audit->record('USER_CREATED', 'users', $user->id, 'Account created with role '.$user->role.'.');
        });

        return redirect()->route('admin.manage', 'users')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('manage-system');

        return view('admin.user-form', compact('user'));
    }

    public function update(Request $request, User $user, AdminAuditService $audit): RedirectResponse
    {
        Gate::authorize('manage-system');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);
        DB::transaction(function () use ($user, $data, $audit): void {
            $user->fill($data);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
            $user->profile()->updateOrCreate(['user_id' => $user->id], ['full_name' => $user->name]);
            $audit->record('USER_UPDATED', 'users', $user->id, 'Account details updated.');
        });

        return redirect()->route('admin.manage', 'users')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user, AdminAuditService $audit): RedirectResponse
    {
        Gate::authorize('manage-system');
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account.');
        DB::transaction(function () use ($user, $audit): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($user->crops()->exists() || $user->devices()->exists() || $user->conversations()->exists() || DB::table('alerts')->where('user_id', $user->id)->exists(), 422, 'This account owns farm records. Retain the account to preserve its history.');
            $audit->record('USER_DELETED', 'users', $user->id, 'Account deleted by an administrator.');
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return redirect()->route('admin.manage', 'users')->with('success', 'User deleted.');
    }
}
