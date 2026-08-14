<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('roles')->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => Role::query()->orderBy('name')->get(),
            'currentRole' => null,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => $data['is_active'],
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'currentRole' => $user->roles->first()?->name,
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // An admin locking themselves out mid-service would be unrecoverable
        // without database access.
        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'],
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(Auth::user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Staff who have taken orders are deactivated: their name stays on the
        // orders they rang up.
        if ($user->orders()->exists()) {
            $user->update(['is_active' => false]);

            return redirect()->route('users.index')
                ->with('status', "{$user->name} has order history, so the account was deactivated instead of deleted.");
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }
}
