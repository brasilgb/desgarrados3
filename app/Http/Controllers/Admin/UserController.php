<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only('q', 'role', 'state');
        $query = User::query();
        if (filled($filters['q'] ?? null)) {
            $term = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where(fn ($users) => $users->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('username', 'like', $term));
        }
        if (in_array($filters['role'] ?? '', array_map(fn (UserRole $role) => $role->value, UserRole::cases()), true)) {
            $query->where('role', $filters['role']);
        }
        if (in_array($filters['state'] ?? '', ['active', 'inactive'], true)) {
            $query->where('is_active', $filters['state'] === 'active');
        }

        return Inertia::render('admin/users/index', [
            'users' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/users/form', ['user' => null, 'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::forceCreate($request->safe()->except('password') + [
            'password' => $request->validated('password'),
            'email_verified_at' => null,
            'is_active' => true,
        ]);

        return to_route('admin.users.index')->with('status', 'Usuário criado.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('admin/users/form', ['user' => $user, 'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $role = UserRole::from($data['role']);
        $active = (bool) $data['is_active'];

        DB::transaction(function () use ($user, $data, $role, $active): void {
            $emailChanged = $user->email !== $data['email'];

            if ($user->role === UserRole::Admin && $user->is_active && $emailChanged) {
                $otherVerifiedAdmins = User::query()->where('role', UserRole::Admin->value)->where('is_active', true)->whereNotNull('email_verified_at')->whereKeyNot($user->id)->lockForUpdate()->count();
                abort_if($otherVerifiedAdmins === 0, 422, 'Crie outro administrador verificado antes de alterar o e-mail do único administrador.');
            }

            if ($user->role === UserRole::Admin && $user->is_active && ($role !== UserRole::Admin || ! $active)) {
                $otherActiveAdmins = User::query()->where('role', UserRole::Admin->value)->where('is_active', true)->whereKeyNot($user->id)->lockForUpdate()->count();
                abort_if($otherActiveAdmins === 0, 422, 'O último administrador ativo não pode ser rebaixado ou desativado.');
            }

            $user->fill(collect($data)->except(['role', 'is_active'])->all());
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            $user->forceFill(['role' => $role, 'is_active' => $active]);
            $user->save();
        }, 3);

        return to_route('admin.users.index')->with('status', 'Usuário atualizado.');
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if (! $user->is_active || ! $user->email_verified_at) {
            return back()->with('error', 'A redefinição exige uma conta ativa e com e-mail verificado.');
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', $status === Password::RESET_LINK_SENT
            ? 'Link de redefinição enviado para '.$user->email.'.'
            : 'Não foi possível enviar o link agora. Aguarde e tente novamente.');
    }
}
