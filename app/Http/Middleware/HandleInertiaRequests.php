<?php

namespace App\Http\Middleware;

use App\Enums\SidebarModule;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user?->loadMissing('role.modulePermissions');
        $modules = SidebarModule::options();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only('id', 'full_name', 'email', 'force_password_change', 'role_id'),
                    'role_name' => $user->role?->name,
                ] : null,
                'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
                'modules' => collect($modules)
                    ->filter(fn (array $module): bool => $user?->role?->canAccessModule($module['value']) ?? false)
                    ->values()
                    ->all(),
                'notifications' => $this->notificationsFor($user),
            ],
            'moduleOptions' => $modules,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'status' => session('status'),
            ],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, unread_count: int}
     */
    private function notificationsFor(?User $user): array
    {
        if ($user === null) {
            return ['items' => [], 'unread_count' => 0];
        }

        return [
            'items' => $user->unreadNotifications()
                ->latest()
                ->limit(15)
                ->get()
                ->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? null,
                    'title' => $notification->data['title'] ?? null,
                    'message' => $notification->data['message'] ?? null,
                    'url' => $notification->data['url'] ?? null,
                    'created_at' => $notification->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }
}
