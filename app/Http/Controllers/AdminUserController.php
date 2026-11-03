<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $admins = User::query()
            ->whereRaw('LOWER(email) <> ?', [User::SUPER_ADMIN_EMAIL])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return view('admin-users.index', compact('admins'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        abort_if($data['email'] === User::SUPER_ADMIN_EMAIL, 422, 'The super-admin account is reserved.');
        User::create($data);

        return redirect()->route('users.index')->with('message', 'Admin account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);
        abort_if($data['email'] === User::SUPER_ADMIN_EMAIL, 422, 'The super-admin account is reserved.');
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);

        return redirect()->route('users.index')->with('message', 'Admin account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin() || (int) $user->id === (int) $request->user()->id, 403);
        $user->delete();

        return redirect()->route('users.index')->with('message', 'Admin account deleted.');
    }
}
