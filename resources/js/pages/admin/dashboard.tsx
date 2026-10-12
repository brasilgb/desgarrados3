import { Head, Link, usePage } from '@inertiajs/react';

type Props = {
    currentUser: { name: string; role: string };
    capabilities: { accessAdmin: boolean; manageUsers: boolean };
    stats: { publications: number; published: number; drafts: number; scheduled: number; categories: number; media: number; submissions: number; submissionTotals: Record<string, number> };
};

export default function AdminDashboard() {
    const { currentUser, capabilities, stats } = usePage<Props>().props;
    const pending = stats.submissions;
    const cards = [
        { label: 'Relatos aguardando moderação', value: pending, href: '/admin/submissions', action: 'Revisar relatos' },
        { label: 'Publicações em rascunho', value: stats.drafts, href: '/admin/publications?status=draft', action: 'Continuar redação' },
        { label: 'Publicações agendadas', value: stats.scheduled, href: '/admin/publications?status=scheduled', action: 'Ver agenda' },
        { label: 'Conteúdo publicado', value: stats.published, href: '/admin/publications?status=published', action: 'Ver publicações' },
    ];

    return <>
        <Head title="Painel da redação" />
        <section className="space-y-8">
            <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Redação Desgarrados</p><h1 className="mt-2 font-serif text-3xl font-bold md:text-4xl">Bom trabalho, {currentUser.name}</h1><p className="mt-2 text-muted-foreground">Fila editorial e conteúdos do portal em um só lugar.</p></div><Link href="/admin/publications/create" className="admin-button min-h-11 px-5">Nova publicação</Link></div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">{cards.map((card, index) => <article key={card.label} className={`rounded-xl border bg-card p-5 ${index === 0 && pending > 0 ? 'border-amber-400 ring-1 ring-amber-200' : 'border-border'}`}><p className="text-sm font-medium text-muted-foreground">{card.label}</p><p className="mt-3 text-4xl font-bold text-primary">{card.value}</p><Link href={card.href} className="mt-4 inline-block text-sm font-semibold text-primary underline underline-offset-4">{card.action}</Link></article>)}<article className="rounded-xl border border-border bg-card p-5"><p className="text-sm font-medium text-muted-foreground">Biblioteca de mídia</p><p className="mt-3 text-4xl font-bold text-primary">{stats.media}</p><Link href="/admin/media" className="mt-4 inline-block text-sm font-semibold text-primary underline underline-offset-4">Organizar arquivos</Link></article></div>
            <div className="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                <section className="rounded-2xl bg-[#31453b] p-6 text-white md:p-8"><p className="text-xs font-bold uppercase tracking-[.2em] text-[#e6b887]">Trabalho editorial</p><h2 className="mt-3 font-serif text-2xl font-bold">Conteúdo com contexto e fonte.</h2><p className="mt-2 max-w-xl text-sm leading-6 text-stone-200">Prepare uma história, organize sua coleção ou revise um relato enviado pela comunidade.</p><div className="mt-6 flex flex-wrap gap-3"><Link href="/admin/publications/create" className="rounded-lg bg-[#e6b887] px-5 py-3 font-semibold text-[#28271f]">Escrever publicação</Link><Link href="/admin/submissions" className="rounded-lg border border-white/40 px-5 py-3 font-semibold text-white">Fila de relatos ({pending})</Link></div></section>
                <section className="rounded-2xl border border-border bg-card p-6 md:p-8"><p className="text-xs font-bold uppercase tracking-[.2em] text-[#bb623e]">Acervo e equipe</p><h2 className="mt-3 font-serif text-2xl font-bold">Organização editorial</h2><p className="mt-2 text-sm leading-6 text-muted-foreground">{stats.published} publicações no portal · {stats.categories} assuntos · {stats.media} arquivos na biblioteca.</p><div className="mt-5 flex flex-wrap gap-x-5 gap-y-3 text-sm font-semibold"><Link href="/admin/collections" className="underline">Coleções</Link><Link href="/admin/series" className="underline">Séries</Link><Link href="/admin/sayings" className="underline">Ditados</Link><Link href="/admin/media" className="underline">Biblioteca</Link>{capabilities.manageUsers && <Link href="/admin/users" className="underline">Usuários e permissões</Link>}</div></section>
            </div>
        </section>
    </>;
}
