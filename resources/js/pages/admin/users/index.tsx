import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminPagination from '@/components/admin-pagination';

type UserRow = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    email_verified_at: string | null;
    role: string;
    is_active: boolean;
};
type Role = { value: string; label: string };
type Props = {
    users: { data: UserRow[]; links: { url: string | null; label: string; active: boolean }[] };
    roles: Role[];
    filters: { q?: string; role?: string; state?: string };
};

export default function UsersIndex() {
    const { users, roles, filters } = usePage<Props>().props;

    return <>
        <Head title="Usuários e permissões" />
        <header className="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Administração</p>
                <h1 className="mt-1 text-3xl font-semibold">Usuários e permissões</h1>
                <p className="mt-1 text-muted-foreground">Gerencie acesso, papéis e situação das contas.</p>
            </div>
            <Link href="/admin/users/create" className="admin-button">Criar usuário</Link>
        </header>

        <form method="get" action="/admin/users" className="mt-6 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-[1fr_12rem_12rem_auto_auto]">
            <label className="sr-only" htmlFor="users-search">Buscar usuário</label>
            <input id="users-search" name="q" defaultValue={filters.q} placeholder="Nome, e-mail ou usuário" className="rounded-lg border-input" />
            <label className="sr-only" htmlFor="users-role">Filtrar por papel</label>
            <select id="users-role" name="role" defaultValue={filters.role ?? ''} className="rounded-lg border-input">
                <option value="">Todos os papéis</option>
                {roles.map(role => <option key={role.value} value={role.value}>{role.label}</option>)}
            </select>
            <label className="sr-only" htmlFor="users-state">Filtrar por estado</label>
            <select id="users-state" name="state" defaultValue={filters.state ?? ''} className="rounded-lg border-input">
                <option value="">Todos os estados</option>
                <option value="active">Ativos</option>
                <option value="inactive">Desativados</option>
            </select>
            <button className="admin-button">Buscar</button>
            <Link href="/admin/users" className="admin-button-secondary">Limpar</Link>
        </form>

        <div className="mt-5 overflow-x-auto rounded-xl border border-border bg-card">
            <table className="w-full min-w-[900px] text-left text-sm">
                <thead className="border-b border-border bg-muted text-muted-foreground">
                    <tr>
                        <th scope="col" className="px-4 py-3">Nome</th>
                        <th scope="col" className="px-4 py-3">E-mail</th>
                        <th scope="col" className="px-4 py-3">Papel</th>
                        <th scope="col" className="px-4 py-3">Situação</th>
                        <th scope="col" className="px-4 py-3"><span className="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    {users.data.map(user => {
                        const canResetPassword = user.is_active && Boolean(user.email_verified_at);

                        return <tr key={user.id} className="border-b border-border last:border-0">
                            <td className="px-4 py-3 font-medium">
                                {user.name}
                                {user.username && <span className="mt-0.5 block text-xs font-normal text-muted-foreground">@{user.username}</span>}
                            </td>
                            <td className="px-4 py-3">{user.email}</td>
                            <td className="px-4 py-3">{roles.find(role => role.value === user.role)?.label ?? user.role}</td>
                            <td className="px-4 py-3">
                                <div className="flex flex-wrap gap-2">
                                    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${user.is_active ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'}`}>
                                        {user.is_active ? 'Ativo' : 'Desativado'}
                                    </span>
                                    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${user.email_verified_at ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'}`}>
                                        {user.email_verified_at ? 'E-mail verificado' : 'E-mail pendente'}
                                    </span>
                                </div>
                            </td>
                            <td className="px-4 py-3 text-right">
                                <div className="flex items-center justify-end gap-4">
                                    <button
                                        type="button"
                                        disabled={!canResetPassword}
                                        title={canResetPassword ? undefined : 'A conta precisa estar ativa e com e-mail verificado.'}
                                        onClick={() => {
                                            if (confirm(`Enviar um link de redefinição de senha para ${user.email}?`)) {
                                                router.post(`/admin/users/${user.id}/password-reset`);
                                            }
                                        }}
                                        className="text-sm font-medium text-muted-foreground underline underline-offset-2 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                                    >Redefinir senha</button>
                                    <Link href={`/admin/users/${user.id}/edit`} className="font-semibold text-primary underline underline-offset-2">Editar</Link>
                                </div>
                            </td>
                        </tr>;
                    })}
                    {users.data.length === 0 && <tr><td colSpan={5} className="px-4 py-12 text-center text-muted-foreground">Nenhum usuário corresponde a esses filtros.</td></tr>}
                </tbody>
            </table>
        </div>
        <div className="mt-5"><AdminPagination links={users.links} label="Paginação de usuários" /></div>
    </>;
}
