<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateFirstAdministrator extends Command
{
    protected $signature = 'desgarrados:admin-create {--force : Permite iniciar o procedimento em produção após confirmação textual}';

    protected $description = 'Cria o primeiro administrador ou promove uma conta existente pelo terminal.';

    public function handle(): int
    {
        if (! $this->confirmProductionOperation()) {
            return self::FAILURE;
        }

        try {
            return Cache::lock('desgarrados:first-administrator', 3600)->block(5, fn (): int => $this->createOrPromote());
        } catch (LockTimeoutException) {
            $this->components->error('Outro procedimento de criação de administrador está em andamento. Tente novamente.');

            return self::FAILURE;
        }
    }

    private function createOrPromote(): int
    {
        if (User::query()->where('role', UserRole::Admin->value)->exists()) {
            $this->components->error('Já existe uma conta com papel de administrador. Nenhuma conta foi alterada.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->ask('E-mail')));
        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing) {
            return $this->promoteExistingAccount($existing);
        }

        return $this->createNewAdministrator($email);
    }

    private function confirmProductionOperation(): bool
    {
        if (! app()->isProduction()) {
            return true;
        }

        if (! $this->option('force')) {
            $this->components->error('Em produção, informe --force e confirme a operação digitando a frase solicitada.');

            return false;
        }

        $phrase = $this->ask('Digite CRIAR ADMINISTRADOR EM PRODUÇÃO para continuar');

        if ($phrase !== 'CRIAR ADMINISTRADOR EM PRODUÇÃO') {
            $this->components->error('Confirmação incorreta. Nenhuma conta foi alterada.');

            return false;
        }

        return true;
    }

    private function promoteExistingAccount(User $user): int
    {
        if (! $user->is_active || ! $user->email_verified_at) {
            $this->components->error('A conta existente precisa estar ativa e com e-mail verificado. Nenhum dado foi alterado.');

            return self::FAILURE;
        }

        if (! $this->confirm("Promover {$user->email} para administrador? Nome, e-mail, senha e demais dados serão preservados.", false)) {
            $this->components->info('Operação cancelada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['role' => UserRole::Admin])->save();
        });

        $this->components->info('Conta existente promovida a administrador. Os demais dados foram preservados.');

        return self::SUCCESS;
    }

    private function createNewAdministrator(string $email): int
    {
        $name = trim((string) $this->ask('Nome'));
        $password = $this->secret('Senha');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()->min(8)->mixedCase()->letters()->numbers()->symbols()],
        ], [
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        User::forceCreate([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->components->info('Administrador criado.');

        return self::SUCCESS;
    }
}
