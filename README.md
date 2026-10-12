# Desgarrados Next

Fundação independente em Laravel 13, Inertia, React, TypeScript, Vite e Tailwind. O cadastro público fica desligado; usuários entram por convite administrativo.

## Desenvolvimento local

Requisitos: PHP 8.4+, Composer e Node/npm.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
php artisan desgarrados:admin-create
php artisan serve
```

O banco de desenvolvimento padrão é o SQLite independente em `database/database.sqlite`. PHPUnit usa SQLite em memória. O comando pede e-mail, nome e senha pelo terminal; a senha não aparece na tela. Exige senha com no mínimo 8 caracteres, letras maiúsculas e minúsculas, números e símbolos; em produção, também aplica a verificação de senha comprometida configurada no Laravel. Se o e-mail já pertencer a uma conta ativa e verificada, o comando solicita confirmação para promovê-la, preservando nome, e-mail e senha. O comando para se já houver uma conta administradora.

Em produção, o comando exige `--force` e que o operador digite `CRIAR ADMINISTRADOR EM PRODUÇÃO`. Rode-o apenas no ambiente e banco previamente autorizados. Para criar o primeiro administrador nesse ambiente, execute no terminal responsável: `php artisan desgarrados:admin-create --force` e siga as confirmações interativas. O comando não imprime nem registra credenciais.

Para homologar em MySQL/MariaDB local, crie antes o banco `desgarrados_local` e um usuário local com acesso a ele. Use `.env.mysql.example` como referência para as variáveis `DB_*` no `.env`, então rode `php artisan migrate --force`. Aplique migrations em um banco local autorizado; elas não apagam tabelas existentes. Os sete textos fictícios são inseridos por `php artisan db:seed` somente em `local` ou `testing`; em outros ambientes o seeder principal não inclui conteúdo demonstrativo.

Rotinas de qualidade:

```sh
vendor/bin/pint --test
php artisan test
npm run types:check
npm run build
composer audit
npm audit
```
