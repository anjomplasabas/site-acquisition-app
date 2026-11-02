<?php

namespace App\Http\Controllers;

use App\Events\AuditLogged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Profile/Edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ], [
            'full_name.regex' => 'Full name may only contain letters, spaces, hyphens, apostrophes, and periods.',
        ]);

        if ($data['full_name'] === $user->full_name && $data['email'] === $user->email) {
            $request->attributes->set('_audit_skip', true);

            return back()->with('success', 'No changes were made.');
        }

        $user->update($data);

        AuditLogged::emit('updated', 'Updated profile', $user);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => $request->password,
            'force_password_change' => false,
        ]);

        AuditLogged::emit('updated', 'Changed password', $user);

        return back()->with('success', 'Password changed successfully.');
    }
}
