import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

type Role = { value: string; label: string };
type UserRecord = { id: number; name: string; username: string | null; email: string; role: string; is_active: boolean };
type Props = { user: UserRecord | null; roles: Role[] };

export default function UserForm() {
    const { user, roles } = usePage<Props>().props;
    const form = useForm({ name: user?.name ?? '', username: user?.username ?? '', email: user?.email ?? '', role: user?.role ?? 'user', is_active: user?.is_active ?? true, password: '' });
    const formErrors = form.errors as Record<string, string>;
    const submit = (event: FormEvent) => { event.preventDefault(); if (user) form.put(`/admin/users/${user.id}`); else form.post('/admin/users'); };
    return <><Head title={user ? 'Editar usuário' : 'Criar usuário'} /><div className="max-w-2xl"><Link href="/admin/users" className="text-sm underline">← Usuários</Link><h1 className="mt-4 text-3xl font-semibold">{user ? 'Editar usuário' : 'Criar usuário'}</h1><form onSubmit={submit} className="mt-6 space-y-5 rounded-xl border border-border bg-card p-6">{([['name', 'Nome'], ['username', 'Nome de usuário'], ['email', 'E-mail']] as const).map(([key, label]) => <label key={key} className="block text-sm font-semibold">{label}<input className="mt-2 block w-full rounded-lg border-input" value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />{form.errors[key] && <span className="mt-1 block text-destructive">{form.errors[key]}</span>}</label>)}<label className="block text-sm font-semibold">Papel<select className="mt-2 block w-full rounded-lg border-input" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>{roles.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}</select>{form.errors.role && <span className="mt-1 block text-destructive">{form.errors.role}</span>}</label>{user ? <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />Conta ativa</label> : <label className="block text-sm font-semibold">Senha inicial<input type="password" autoComplete="new-password" className="mt-2 block w-full rounded-lg border-input" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />{form.errors.password && <span className="mt-1 block text-destructive">{form.errors.password}</span>}</label>}<div className="flex items-center gap-4"><button disabled={form.processing} className="admin-button">Salvar</button>{formErrors.user && <span className="text-sm text-destructive">{formErrors.user}</span>}</div></form></div></>;
}
