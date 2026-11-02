<?php

namespace App\Http\Controllers;

use App\Events\AuditLogged;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Mail\UserCredentialsMail;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::with(['role', 'department'])
            ->when($request->search, fn ($q) => $q->where(fn ($search) => $search
                ->where('full_name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->role, fn ($q) => $q->where('role_id', $request->role))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'status', 'role'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $password = Str::random(12);

        $user = User::create(array_merge($request->validated(), [
            'password' => $password,
            'force_password_change' => true,
        ]));

        AuditLogged::emit('created', "Created user {$user->full_name}", $user);

        try {
            Mail::to($user->email)->send(new UserCredentialsMail($user, $password));
        } catch (\Throwable $e) {
            report($e);

            return redirect('/users')
                ->with('error', 'User created, but the credentials email could not be sent. Use "Reset Password" to resend it.');
        }

        return redirect('/users')->with('success', 'User created successfully.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $password = Str::random(12);

        $user->update([
            'password' => $password,
            'force_password_change' => true,
        ]);

        AuditLogged::emit('password_reset', "Reset password for {$user->full_name}", $user);

        try {
            Mail::to($user->email)->send(new UserCredentialsMail($user, $password, isReset: true));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Password was reset, but the notification email could not be sent to the user.');
        }

        return back()->with('success', 'Password reset and sent to the user.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Mirrors ProfileController::update()'s no-op check — without
        // it, re-submitting the form with no actual edits still writes an audit log entry,
        // since AuditMiddleware::terminate() logs every successful non-GET request purely from
        // the route name, with no visibility into whether anything downstream actually changed.
        $unchanged = ! array_key_exists('password', $data)
            && (string) $data['full_name'] === (string) $user->full_name
            && (string) $data['email'] === (string) $user->email
            && (string) $data['role_id'] === (string) $user->role_id
            && (string) $data['department_id'] === (string) $user->department_id
            && (string) $data['status'] === (string) $user->status;

        if ($unchanged) {
            // Must go through the request() helper, not the injected $request — $request here
            // is the UpdateUserRequest instance FormRequestServiceProvider builds fresh from a
            // copy of the container-bound request's data; it's a different object, and
            // attributes set on it never propagate back, so AuditMiddleware::terminate()'s
            // request()->attributes->get() check would never see them (see resolveSubject()'s
            // doc comment below for the same gotcha with _audit_subject).
            request()->attributes->set('_audit_skip', true);

            return redirect('/users')->with('success', 'No changes were made.');
        }

        $user->update($data);

        AuditLogged::emit('updated', "Updated user {$user->full_name}", $user);

        return redirect('/users')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        AuditLogged::emit('deleted', "Deleted user {$user->full_name}", $user);

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
