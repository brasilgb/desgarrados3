<?php

namespace App\Http\Middleware;

use App\Models\PortalSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')],
            'portalSettings' => PortalSetting::query()->pluck('value', 'key'),
            'auth' => ['user' => $request->user()?->only(['id', 'name', 'email', 'role', 'is_active'])],
            'capabilities' => [
                'accessAdmin' => $request->user() ? Gate::allows('access-admin') : false,
                'manageUsers' => $request->user() ? Gate::allows('manage-users') : false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
