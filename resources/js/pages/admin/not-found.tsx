import { Head, Link } from '@inertiajs/react';

export default function AdminNotFound() {
    return <><Head title="Seção não encontrada" /><section className="mx-auto max-w-xl rounded-2xl border bg-card p-8 text-center"><p className="text-xs font-bold uppercase tracking-widest text-secondary-foreground">404 · Redação</p><h1 className="mt-3 font-serif text-3xl font-bold">Esta seção não existe.</h1><p className="mt-3 text-muted-foreground">Use a navegação para voltar ao trabalho editorial.</p><Link href="/admin" className="admin-button mt-6">Voltar ao painel</Link></section></>;
}
