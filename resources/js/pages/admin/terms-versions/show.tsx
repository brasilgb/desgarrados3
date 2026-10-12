import { Head, Link } from '@inertiajs/react';

type Props = { version: { id: number; label: string; content_hash: string; source: string; title: string; body: string; created_at: string }; acceptances: number };

export default function TermsVersionShow({ version, acceptances }: Props) {
    return <><Head title={`Termos ${version.label}`} /><div className="w-full min-w-0 space-y-6">
        <Link href="/admin/submissions" className="text-sm underline">← Relatos e moderação</Link>
        <div><p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">Termos de envio · versão aceita</p><h1 className="mt-1 font-serif text-3xl font-bold">{version.title}</h1></div>
        <dl className="grid gap-3 rounded-xl border bg-card p-5 text-sm sm:grid-cols-2">
            <div><dt className="font-semibold">Versão</dt><dd>{version.label}</dd></div>
            <div><dt className="font-semibold">Registrada em</dt><dd>{new Date(version.created_at).toLocaleString('pt-BR')}</dd></div>
            <div><dt className="font-semibold">Origem do texto</dt><dd>{version.source === 'pagina' ? 'Página editada no painel' : 'Texto padrão do portal'}</dd></div>
            <div><dt className="font-semibold">Aceites registrados</dt><dd>{acceptances}</dd></div>
            <div className="sm:col-span-2"><dt className="font-semibold">Impressão digital (SHA-256)</dt><dd className="break-all font-mono text-xs">{version.content_hash}</dd></div>
        </dl>
        <p className="text-sm text-muted-foreground">Cópia imutável do texto exibido no momento do aceite. Edições posteriores da página criam novas versões e não alteram esta.</p>
        <article className="editorial-body rounded-xl border bg-card p-5 md:p-8" dangerouslySetInnerHTML={{ __html: version.body }} />
    </div></>;
}
