<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdministrationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function test_guests_users_and_authorized_roles_are_gated_from_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole(UserRole::User))->get('/admin')->assertForbidden();
        $this->actingAs($this->userWithRole(UserRole::Editor))->get('/admin')->assertOk();
        $this->actingAs($this->userWithRole(UserRole::Admin))->get('/admin')->assertOk();
    }

    public function test_editors_cannot_manage_users_but_admin_can_create_and_update_them(): void
    {
        $editor = $this->userWithRole(UserRole::Editor);
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();

        $admin = $this->userWithRole(UserRole::Admin);
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $response = $this->post('/admin/users', [
            'name' => 'Equipe Editorial', 'username' => 'equipe-editorial', 'email' => 'editor@example.test',
            'role' => 'editor', 'password' => 'Senha-forte-2026!',
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect('/admin/users');

        $created = User::where('email', 'editor@example.test')->firstOrFail();
        $this->assertSame(UserRole::Editor, $created->role);
        $this->assertTrue($created->is_active);
        $this->assertNull($created->email_verified_at);

        $this->put('/admin/users/'.$created->id, [
            'name' => 'Equipe Atualizada', 'username' => 'equipe-editorial', 'email' => $created->email,
            'role' => 'user', 'is_active' => false,
        ])->assertRedirect('/admin/users');
        $this->assertSame(UserRole::User, $created->refresh()->role);
        $this->assertFalse($created->is_active);
    }

    public function test_admin_can_send_a_password_reset_link_only_to_an_active_verified_account(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(UserRole::Admin);
        $target = User::factory()->create(['email' => 'reset@example.test']);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->post(route('admin.users.password-reset', $target))
            ->assertRedirect('/admin/users')
            ->assertSessionHas('success');

        Notification::assertSentTo($target, ResetPassword::class);

        $inactive = User::factory()->create(['email' => 'inactive@example.test', 'is_active' => false]);
        $this->post(route('admin.users.password-reset', $inactive))
            ->assertRedirect('/admin/users')
            ->assertSessionHas('error');
        Notification::assertNotSentTo($inactive, ResetPassword::class);
    }

    public function test_public_registration_is_disabled_and_login_rate_limited(): void
    {
        $this->get('/register')->assertNotFound();
        $user = $this->userWithRole(UserRole::Admin, ['email' => 'admin@example.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertStatus(429);
    }

    public function test_inactive_accounts_cannot_login_or_continue_an_existing_session(): void
    {
        $user = $this->userWithRole(UserRole::Admin, ['email' => 'admin@example.test']);
        $user->forceFill(['is_active' => false])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($user)->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_unverified_accounts_cannot_access_the_admin_panel(): void
    {
        $unverifiedAdmin = $this->userWithRole(UserRole::Admin);
        $unverifiedAdmin->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($unverifiedAdmin)->get('/admin')->assertRedirect(route('verification.notice'));
    }

    public function test_roles_are_not_mass_assignable_and_last_active_admin_is_protected(): void
    {
        $user = User::query()->create([
            'name' => 'Conta comum', 'email' => 'user@example.test', 'password' => 'Senha-forte-2026!',
            'role' => 'admin', 'is_active' => false,
        ]);
        $user->refresh();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Senha-forte-2026!', $user->password));

        $admin = $this->userWithRole(UserRole::Admin, ['email' => 'only-admin@example.test']);
        $this->actingAs($admin)->patch('/settings/profile', [
            'name' => $admin->name, 'email' => 'new-email@example.test',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->put('/admin/users/'.$admin->id, [
            'name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email,
            'role' => 'editor', 'is_active' => true,
        ])->assertStatus(422);

        $this->actingAs($admin)->put('/admin/users/'.$admin->id, [
            'name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email,
            'role' => 'admin', 'is_active' => false,
        ])->assertStatus(422);

        $this->actingAs($admin)->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_duplicate_email_username_and_invalid_role_are_rejected(): void
    {
        $admin = $this->userWithRole(UserRole::Admin, ['email' => 'admin@example.test']);
        User::factory()->create(['email' => 'taken@example.test', 'username' => 'taken']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Duplicado', 'email' => 'taken@example.test', 'username' => 'taken',
            'role' => 'root', 'password' => 'Senha-forte-2026!',
        ])->assertSessionHasErrors(['email', 'username', 'role']);
    }

    public function test_first_admin_command_uses_secure_terminal_input_and_marks_email_verified(): void
    {
        $this->artisan('desgarrados:admin-create')
            ->expectsQuestion('E-mail', 'Primeira@Example.test')
            ->expectsQuestion('Nome', 'Primeira Admin')
            ->expectsQuestion('Senha', 'Senha-forte-2026!')
            ->expectsOutputToContain('Administrador criado.')
            ->doesntExpectOutputToContain('Senha-forte-2026!')
            ->assertExitCode(0);

        $admin = User::where('email', 'primeira@example.test')->firstOrFail();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_first_admin_command_promotes_an_existing_verified_account_only_after_confirmation(): void
    {
        $existing = User::factory()->create(['name' => 'Pessoa da redação', 'email' => 'editor@example.test']);
        $originalPassword = $existing->password;

        $this->artisan('desgarrados:admin-create')
            ->expectsQuestion('E-mail', 'EDITOR@EXAMPLE.TEST')
            ->expectsConfirmation('Promover editor@example.test para administrador? Nome, e-mail, senha e demais dados serão preservados.', 'yes')
            ->expectsOutputToContain('Os demais dados foram preservados.')
            ->assertExitCode(0);

        $existing->refresh();
        $this->assertSame(UserRole::Admin, $existing->role);
        $this->assertSame('Pessoa da redação', $existing->name);
        $this->assertSame('editor@example.test', $existing->email);
        $this->assertSame($originalPassword, $existing->password);
    }

    public function test_first_admin_command_refuses_a_second_administrator(): void
    {
        $this->userWithRole(UserRole::Admin);

        $this->artisan('desgarrados:admin-create')
            ->expectsOutputToContain('Já existe uma conta com papel de administrador.')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->where('role', UserRole::Admin->value)->count());
    }

    public function test_first_admin_command_requires_an_explicit_production_safeguard(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('desgarrados:admin-create')
            ->expectsOutputToContain('Em produção, informe --force')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_first_admin_command_can_proceed_in_production_only_after_force_and_phrase(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $existing = User::factory()->create(['name' => 'Responsável de teste', 'email' => 'responsavel@example.test']);

        $this->artisan('desgarrados:admin-create', ['--force' => true])
            ->expectsQuestion('Digite CRIAR ADMINISTRADOR EM PRODUÇÃO para continuar', 'CRIAR ADMINISTRADOR EM PRODUÇÃO')
            ->expectsQuestion('E-mail', 'responsavel@example.test')
            ->expectsConfirmation('Promover responsavel@example.test para administrador? Nome, e-mail, senha e demais dados serão preservados.', 'yes')
            ->expectsOutputToContain('Os demais dados foram preservados.')
            ->assertExitCode(0);

        $this->assertSame(UserRole::Admin, $existing->refresh()->role);
    }

    public function test_first_admin_command_rejects_a_weak_password_without_creating_a_user(): void
    {
        $this->artisan('desgarrados:admin-create')
            ->expectsQuestion('E-mail', 'fraca@example.test')
            ->expectsQuestion('Nome', 'Conta de teste')
            ->expectsQuestion('Senha', 'senha')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'fraca@example.test']);
    }
}
