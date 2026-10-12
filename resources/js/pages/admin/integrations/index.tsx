import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock3, ExternalLink, LoaderCircle, Radio, RefreshCw, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

type Integration = { key: string; name: string; data_type: string; source_url: string; license: string | null; availability: string; enabled: boolean; records_count: number; last_synced_at: string | null; last_attempted_at: string | null; next_sync_at: string | null; last_error: string | null };
type Props = { integrations: Integration[]; flash?: { success?: string } };
const date = (value: string | null) => value ? new Date(value).toLocaleString('pt-BR') : 'Ainda não sincronizada';
const availabilityLabel: Record<string, string> = { available: 'Disponível para sincronização', credentials_and_paid_tier: 'Acesso requer credencial e pode gerar cobrança', no_stable_public_api_confirmed: 'Sem API pública estável confirmada', access_key_required: 'Chave de acesso necessária' };

export default function RegionalIntegrations({ integrations }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [pending, setPending] = useState<string | null>(null);
    function sync(key: string) {
        setPending(key);
        router.post(`/admin/integrations/${key}/sync`, {}, { preserveScroll: true, onFinish: () => setPending(null) });
    }
    function toggle(integration: Integration) {
        router.put(`/admin/integrations/${integration.key}`, { enabled: !integration.enabled }, { preserveScroll: true });
    }

    return <><Head title="Fontes e integrações"/><div className="w-full min-w-0 space-y-6"><header><Link href="/admin/rural-calendar" className="text-sm font-semibold underline">← Central Campeira</Link><p className="mt-5 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-primary"><Radio className="h-4 w-4"/>Administração de dados</p><h1 className="mt-2 font-serif text-3xl font-bold text-primary">Fontes e integrações</h1><p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">Confira situação, última sincronização e licença. Sincronizações manuais usam bloqueio contra execuções simultâneas; credenciais nunca são mostradas nesta tela.</p></header>
        {errors.integration && <p role="alert" className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">{errors.integration}</p>}
        <section className="grid gap-4">{integrations.map(integration => { const available = integration.availability === 'available'; return <article key={integration.key} className="rounded-2xl border border-border bg-card p-5 md:p-6"><div className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-wider text-muted-foreground">{integration.data_type}</p><h2 className="mt-1 font-serif text-xl font-bold text-primary">{integration.name}</h2><p className="mt-2 text-sm text-muted-foreground">{availabilityLabel[integration.availability] ?? integration.availability}</p></div><span className={`inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ${available && integration.enabled ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'}`}>{available && integration.enabled ? <CheckCircle2 className="h-4 w-4"/> : <ShieldAlert className="h-4 w-4"/>}{available ? integration.enabled ? 'Ativa' : 'Desativada' : 'Avaliação pendente'}</span></div>
            <div className="mt-5 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><p><span className="block text-xs text-muted-foreground">Última sincronização</span><strong>{date(integration.last_synced_at)}</strong></p><p><span className="block text-xs text-muted-foreground">Registros disponíveis</span><strong>{integration.records_count.toLocaleString('pt-BR')}</strong></p><p><span className="block text-xs text-muted-foreground">Próxima atualização</span><strong>{date(integration.next_sync_at)}</strong></p><p><span className="block text-xs text-muted-foreground">Licença / reutilização</span><strong>{integration.license ?? 'Confirmar com o provedor'}</strong></p></div>
            {integration.last_error && <p className="mt-4 rounded-lg bg-secondary p-3 text-sm text-secondary-foreground"><Clock3 className="mr-1 inline h-4 w-4"/>Última falha: {integration.last_error}</p>}
            <div className="mt-5 flex flex-wrap items-center gap-4 border-t pt-4"><a href={integration.source_url.replace('{ano}', String(new Date().getFullYear())).replace('?uf={UF}', '')} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-semibold underline">Abrir fonte <ExternalLink className="h-4 w-4"/></a>{available && <><label className="ml-auto flex items-center gap-2 text-sm font-semibold"><input type="checkbox" checked={integration.enabled} onChange={() => toggle(integration)}/> Sincronização automática ativa</label><button type="button" disabled={pending === integration.key || !integration.enabled} onClick={() => sync(integration.key)} className="admin-button">{pending === integration.key ? <LoaderCircle className="h-4 w-4 animate-spin"/> : <RefreshCw className="h-4 w-4"/>}Sincronizar agora</button></>}</div>
        </article>; })}</section>
    </div></>;
}
