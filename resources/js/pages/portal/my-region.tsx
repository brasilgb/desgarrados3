import { Link, router } from '@inertiajs/react';
import PortalBreadcrumb from '@/components/portal-breadcrumb';
import { ArrowRight, CalendarDays, ExternalLink, MapPin, Share2, Wheat } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import { getMunicipalityPreference, saveMunicipalityPreference } from '@/lib/location-preference';
import PortalPageHero from '@/components/portal-page-hero';
import PortalSeo from '@/components/portal-seo';

type State = { code: string; name: string };
type MunicipalityOption = { code: string; name: string };
type Story = { id: number; title: string; slug: string; excerpt: string | null; type: string; origin_scope: string; origin_city: string | null; origin_state: string | null; cover_path: string | null; cover_media: { path: string; alt_text: string | null; credit: string | null; width: number | null; height: number | null } | null; published_at: string; author: { name: string } | null; category: { name: string; slug: string } | null };
type DatedEntry = { id: number; slug?: string; activity?: string; name?: string; category?: string; starts_on: string; ends_on?: string | null; reference_period?: string | null; frequency?: string; activity_type?: string; region?: string; source_name: string; source_url: string; verified_at: string; description?: string | null; municipality: string; state: string };
type Holiday = { id: number; name: string; date: string; scope: 'national' | 'state'; state_code: string | null; optional: boolean | null; source_url: string };
type Guide = { id: number; title: string; slug: string; category: string; state_code: string | null; summary: string | null; source_name: string | null; source_url: string | null; reference_date: string | null };
type RegionData = { municipality: { name: string; code: string; state_code: string; state_name: string; region_name: string | null }; cultures: string[]; zarcSyncedAt: string | null; zarcLastError: string | null; activities: DatedEntry[]; events: DatedEntry[]; holidays: Holiday[]; stories: Story[]; stateStories: Story[]; regionalStories: Story[]; stateGuides: Guide[] };
type Props = { states: State[]; municipalities: MunicipalityOption[]; filters: { state: string; municipality_code: string }; regionData: RegionData | null };

const date = (value: string) => new Date(`${value.slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR');
const toolMatches: [RegExp, string, string][] = [
    [/arroz|soja|milho|trigo|feijão|semente/i, 'seed', 'Sementes por área'],
    [/bovin|gado|vaca|leite/i, 'milk', 'Produção de leite'],
    [/ovino|ovelha|lã/i, 'wool', 'Lã ovina'],
    [/uva|viticultura|parreira/i, 'grapes', 'Produção de uvas'],
    [/frango|ave|avicultura/i, 'poultry', 'Indicadores de aves'],
    [/suin|porco/i, 'swine', 'Indicadores de suínos'],
    [/mel|apicultura|colmeia/i, 'honey', 'Produção de mel'],
];

export default function MyRegion({ states, municipalities, filters, regionData }: Props) {
    const [shareMessage, setShareMessage] = useState('');
    useEffect(() => {
        if (new URLSearchParams(window.location.search).has('state') || new URLSearchParams(window.location.search).has('municipality_code')) return;
        const saved = getMunicipalityPreference();
        if (saved && (saved.code !== filters.municipality_code || saved.state !== filters.state)) router.get('/minha-regiao', saved, { preserveScroll: true, preserveState: true, replace: true });
    // Read the shared location preference only on initial entry.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    function changeState(event: ChangeEvent<HTMLSelectElement>) {
        const state = event.target.value;
        router.get('/minha-regiao', state ? { state } : {}, { preserveState: false, preserveScroll: true, replace: true });
    }
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const state = String(data.get('state') ?? '');
        const municipality_code = String(data.get('municipality_code') ?? '');
        if (state && municipality_code) saveMunicipalityPreference({ state, code: municipality_code });
        router.get('/minha-regiao', { state, municipality_code }, { preserveScroll: true });
    }
    async function sharePlace() {
        const url = window.location.href;
        try {
            if (navigator.share) await navigator.share({ title: 'Minha Região · Desgarrados', text: `Uma consulta sobre ${regionData?.municipality.name}, ${regionData?.municipality.state_code}`, url });
            else { await navigator.clipboard.writeText(url); setShareMessage('Endereço copiado para compartilhar.'); }
        } catch { setShareMessage('Não foi possível compartilhar agora. Você pode copiar o endereço do navegador.'); }
    }

    const linkedTopics = regionData ? [
        ...regionData.cultures,
        ...regionData.activities.flatMap(item => [item.activity ?? '', item.category ?? '']),
    ].join(' ') : '';
    const tools = toolMatches.filter(([pattern]) => pattern.test(linkedTopics));
    const calendarHref = regionData ? `/calendario-rural?state=${regionData.municipality.state_code}&municipality_code=${regionData.municipality.code}` : '/calendario-rural';
    const agendaHref = regionData ? `/agenda-regional?state=${regionData.municipality.state_code}&municipality=${encodeURIComponent(regionData.municipality.name)}` : '/agenda-regional';
    const productionHref = regionData ? `/nossa-terra/producao?state=${regionData.municipality.state_code}` : '/nossa-terra/producao';
    const storyGroup = (title: string, scope: string, items: Story[]) => <section className="rounded-xl bg-white/10 p-4"><p className="text-xs font-bold uppercase tracking-wider text-[#d4a476]">{scope}</p><h3 className="mt-1 font-serif text-lg font-bold">{title}</h3>{items.length ? <ul className="mt-3 space-y-3">{items.map(story => <li key={story.id} className="border-t border-white/15 pt-3">{story.cover_path && <img src={`/storage/${story.cover_path}`} alt={story.cover_media?.alt_text || story.title} width={story.cover_media?.width ?? undefined} height={story.cover_media?.height ?? undefined} loading="lazy" decoding="async" sizes="(min-width: 768px) 30vw, 100vw" className="mb-2 aspect-[16/9] w-full rounded-lg object-cover"/>}<p className="text-xs text-[#e5d4bd]">{story.category?.name ?? story.type} · {story.author?.name ?? 'Desgarrados'}</p><Link href={`/publicacao/${story.slug}`} className="mt-1 block font-semibold underline decoration-[#d4a476] underline-offset-4">{story.title}</Link>{story.cover_media?.credit && <p className="mt-1 text-xs text-[#e5d4bd]">Fotografia: {story.cover_media.credit}</p>}{story.excerpt && <p className="mt-1 text-sm leading-5 text-[#e7e9e2]">{story.excerpt}</p>}</li>)}</ul> : <p className="mt-3 text-sm leading-5 text-[#e7e9e2]">Ainda não há conteúdo publicado nesta abrangência.</p>}</section>;

    return <>
        <PortalSeo title="Minha Região · Nossa Terra" description="Informações oficiais, registros locais e histórias reunidos por município e estado do Sul do Brasil." />
        <PortalPageHero image="nossa-terra" icon={MapPin} eyebrow="Minha Região" title="Um retrato da sua terra" intro="Escolha município e estado para reunir informações oficiais, registros locais e histórias ligadas a esse lugar. O que não tiver fonte ou registro aparece como indisponível, sem estimativas geográficas." /><PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, { label: 'Nossa Terra', href: '/nossa-terra' }, { label: 'Minha Região' }]} />
        <main className="mx-auto max-w-7xl space-y-8 px-5 py-9">
            <form onSubmit={submit} className="grid gap-4 rounded-2xl border border-[#ded5c5] bg-[#fbf8f1] p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
                <label className="portal-label">Estado<select name="state" defaultValue={filters.state} onChange={changeState} className="portal-field mt-1.5"><option value="">Escolha um estado</option>{states.map(item => <option key={item.code} value={item.code}>{item.code} · {item.name}</option>)}</select><span className="mt-1 block text-xs font-normal text-stone-500">RS, SC e PR aparecem primeiro.</span></label>
                <label className="portal-label">Município<select name="municipality_code" defaultValue={filters.municipality_code} disabled={!filters.state} className="portal-field mt-1.5"><option value="">{filters.state ? 'Escolha um município' : 'Escolha o estado primeiro'}</option>{municipalities.map(item => <option key={item.code} value={item.code}>{item.name}</option>)}</select><span className="mt-1 block text-xs font-normal text-stone-500">Lista oficial do IBGE; digite o nome para localizar.</span></label>
                <button disabled={!filters.state} className="portal-button self-start sm:mt-7">Ver minha região</button>
            </form>

            {!regionData && <div className="rounded-2xl border border-[#ded5c5] bg-white p-9 text-center"><MapPin className="mx-auto h-9 w-9 text-[#bb623e]"/><h2 className="mt-3 font-serif text-2xl font-bold text-[#31453b]">Comece pelo lugar que você conhece</h2><p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-stone-600">Selecione uma UF e um município. A consulta usa os códigos oficiais do IBGE e deixa a seleção no endereço para você poder voltar ou compartilhar.</p></div>}

            {regionData && <>
                <section aria-labelledby="official-town" className="rounded-2xl bg-[#e8dfcf] p-6"><p className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">IBGE · cadastro territorial</p><h2 id="official-town" className="mt-2 font-serif text-3xl font-bold text-[#31453b]">{regionData.municipality.name}, {regionData.municipality.state_code}</h2><p className="mt-2 text-sm text-stone-700">Código IBGE {regionData.municipality.code} · {regionData.municipality.state_name}{regionData.municipality.region_name ? ` · Região ${regionData.municipality.region_name}` : ''}</p><p className="mt-3 text-xs text-stone-600">Identificação e região oficial do município. Atividades específicas abaixo aparecem apenas quando há registro local verificável.</p></section>

                <div className="flex flex-wrap items-center gap-3"><button type="button" onClick={sharePlace} className="portal-button-secondary"><Share2 className="h-4 w-4"/>Compartilhar esta localidade</button><Link href={`/contribua?origin_city=${encodeURIComponent(regionData.municipality.name)}&origin_state=${regionData.municipality.state_code}`} className="rounded-lg bg-[#bb623e] px-4 py-2 text-sm font-semibold text-white">Compartilhar uma lembrança</Link>{shareMessage && <span role="status" className="text-sm text-stone-600">{shareMessage}</span>}</div>

                <section aria-labelledby="local-production"><header className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">Agricultura e pecuária</p><h2 id="local-production" className="mt-1 font-serif text-2xl font-bold text-[#31453b]">Atividades com registro para este município</h2></div><Link href={calendarHref} className="inline-flex items-center gap-1 text-sm font-semibold underline">Abrir calendário rural <ArrowRight className="h-4 w-4"/></Link></header><div className="mt-4 grid gap-4 lg:grid-cols-2">
                    <article className="portal-card rounded-2xl border border-[#ded5c5] bg-white p-5"><p className="text-xs font-bold uppercase tracking-wider text-[#bb623e]">Zoneamento oficial</p><h3 className="mt-1 font-serif text-xl font-bold text-[#31453b]">Culturas com dados ZARC</h3>{regionData.cultures.length ? <ul className="mt-3 flex flex-wrap gap-2">{regionData.cultures.map(culture => <li key={culture} className="rounded-full bg-[#e8dfcf] px-3 py-1.5 text-sm">{culture}</li>)}</ul> : <p className="mt-3 text-sm leading-6 text-stone-600">{regionData.zarcLastError ? 'A última tentativa de atualização falhou; a cópia local não tem registros para este município.' : regionData.zarcSyncedAt ? 'O arquivo importado da safra disponível não contém registros para este município.' : 'A consulta ZARC ainda não possui uma sincronização disponível.'}</p>}{regionData.zarcSyncedAt && <p className="mt-3 text-xs text-stone-500">Dados sincronizados em {date(regionData.zarcSyncedAt)}{regionData.zarcLastError && ' · última tentativa indisponível'}</p>}<p className="mt-2 text-xs text-stone-500">A lista indica cobertura no conjunto importado, não aptidão nem recomendação de plantio.</p></article>
                    <article className="portal-card rounded-2xl border border-[#ded5c5] bg-white p-5"><p className="text-xs font-bold uppercase tracking-wider text-[#bb623e]">Registros editoriais locais</p><h3 className="mt-1 font-serif text-xl font-bold text-[#31453b]">Calendário rural publicado</h3>{regionData.activities.length ? <ul className="mt-3 space-y-3">{regionData.activities.map(item => <li key={item.id} className="border-t border-stone-100 pt-3"><p className="font-semibold text-[#31453b]">{item.activity} <span className="font-normal text-stone-600">· {item.category}</span></p><p className="mt-1 text-xs text-stone-600">{date(item.starts_on)}{item.ends_on ? ` – ${date(item.ends_on)}` : ''} · {item.reference_period ?? 'Período sem safra informada'}</p><a href={item.source_url} target="_blank" rel="noreferrer" className="mt-1 inline-flex items-center gap-1 text-xs font-semibold underline">{item.source_name} · verificado {date(item.verified_at)} <ExternalLink className="h-3 w-3"/></a></li>)}</ul> : <p className="mt-3 text-sm leading-6 text-stone-600">Ainda não há calendários rurais publicados para este município. Não inferimos as atividades locais pela região.</p>}</article>
                </div></section>

                <section aria-labelledby="local-dates"><header className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">Datas e encontros</p><h2 id="local-dates" className="mt-1 font-serif text-2xl font-bold text-[#31453b]">O que vem pela frente</h2></div><Link href={agendaHref} className="inline-flex items-center gap-1 text-sm font-semibold underline">Abrir agenda regional <ArrowRight className="h-4 w-4"/></Link></header><div className="mt-4 grid gap-4 lg:grid-cols-2"><article className="portal-card rounded-2xl border border-[#ded5c5] bg-white p-5"><h3 className="font-serif text-xl font-bold text-[#31453b]">Festas, feiras e eventos</h3>{regionData.events.length ? <ul className="mt-3 space-y-3">{regionData.events.map(item => <li key={item.id} className="border-t pt-3"><Link href={`/agenda-regional/${item.slug}`} className="font-semibold text-[#31453b] underline">{item.name}</Link><p className="mt-1 text-xs text-stone-600">{date(item.starts_on)}{item.ends_on && item.ends_on !== item.starts_on ? ` – ${date(item.ends_on)}` : ''} · {item.category}</p><a href={item.source_url} target="_blank" rel="noreferrer" className="mt-1 inline-block text-xs font-semibold underline">{item.source_name} · verificado {date(item.verified_at)}</a></li>)}</ul> : <p className="mt-3 text-sm leading-6 text-stone-600">Não há eventos municipais confirmados na agenda publicada.</p>}</article><article className="portal-card rounded-2xl border border-[#ded5c5] bg-white p-5"><h3 className="font-serif text-xl font-bold text-[#31453b]">Feriados até os próximos 12 meses</h3>{regionData.holidays.length ? <ul className="mt-3 space-y-3">{regionData.holidays.map(item => <li key={item.id} className="flex items-start justify-between gap-3 border-t pt-3"><div><p className="text-xs font-bold uppercase tracking-wider text-[#bb623e]">{item.scope === 'national' ? 'Nacional' : `Estadual · ${item.state_code}`}{item.optional === true ? ' · ponto facultativo' : item.optional === false ? ' · feriado' : ''}</p><p className="mt-1 font-semibold text-[#31453b]">{item.name}</p></div><div className="shrink-0 text-right text-xs text-stone-600">{date(item.date)}<a href={item.source_url} target="_blank" rel="noreferrer" className="mt-1 block font-semibold underline">Fonte</a></div></li>)}</ul> : <p className="mt-3 text-sm leading-6 text-stone-600">Não há feriados cadastrados nesse período para a fonte consultada.</p>}</article></div></section>

                <section aria-labelledby="local-culture" className="rounded-2xl bg-[#31453b] p-6 text-white"><p className="text-xs font-bold uppercase tracking-widest text-[#d4a476]">Cultura e memória</p><h2 id="local-culture" className="mt-1 font-serif text-2xl font-bold">Acervo cultural desta região</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-[#e7e9e2]">Os conteúdos estão separados por abrangência editorial para não confundir histórias municipais com referências do estado ou de várias localidades.</p><div className="mt-4 grid gap-3 md:grid-cols-3">{storyGroup(`Conteúdos de ${regionData.municipality.name}`, 'Municipal', regionData.stories)}{storyGroup(`Conteúdos do ${regionData.municipality.state_name}`, 'Estadual', regionData.stateStories)}{storyGroup('Memórias compartilhadas do Sul', 'Regional / várias localidades', regionData.regionalStories)}</div></section>

                {regionData.stateGuides.length > 0 && <section aria-labelledby="state-guides"><header className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">Referências do estado</p><h2 id="state-guides" className="mt-1 font-serif text-2xl font-bold text-[#31453b]">Cadernos produtivos para {regionData.municipality.state_name}</h2><p className="mt-1 text-sm text-stone-600">Conteúdo estadual ou geral; não significa que a atividade ocorra neste município.</p></div><Link href={productionHref} className="text-sm font-semibold underline">Ver todos</Link></header><div className="mt-4 grid gap-3 md:grid-cols-2">{regionData.stateGuides.map(guide => <article key={guide.id} className="rounded-xl border border-[#ded5c5] bg-white p-4"><p className="text-xs font-bold uppercase tracking-wider text-[#bb623e]">{guide.category}</p><h3 className="mt-1 font-serif text-lg font-bold"><Link href={`/nossa-terra/producao/${guide.slug}`} className="text-[#31453b] underline">{guide.title}</Link></h3>{guide.summary && <p className="mt-2 text-sm text-stone-600">{guide.summary}</p>}{guide.source_name && guide.source_url && <a href={guide.source_url} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-xs font-semibold underline">{guide.source_name} · referência {guide.reference_date ? date(guide.reference_date) : 'sem data'} <ExternalLink className="h-3 w-3"/></a>}</article>)}</div></section>}

                <section aria-labelledby="local-tools" className="rounded-2xl border border-[#ded5c5] bg-[#fbf8f1] p-5"><p className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">Serviços da Nossa Terra</p><h2 id="local-tools" className="mt-1 font-serif text-xl font-bold text-[#31453b]">Atalhos para continuar</h2><div className="mt-3 flex flex-wrap gap-3"><Link href={calendarHref} className="rounded-full border px-4 py-2 text-sm font-semibold text-[#31453b] underline">Calendário e ZARC · {regionData.municipality.name}</Link><Link href={agendaHref} className="rounded-full border px-4 py-2 text-sm font-semibold text-[#31453b] underline">Agenda e feriados</Link>{tools.map(([, id, label]) => <Link key={id} href={`/nossa-terra?ferramenta=${id}`} className="inline-flex items-center gap-1 rounded-full border px-4 py-2 text-sm font-semibold text-[#31453b] underline"><Wheat className="h-4 w-4"/>{label}</Link>)}</div></section>
            </>}
            <p className="flex items-center gap-2 text-xs text-stone-500"><CalendarDays className="h-4 w-4"/>A consulta usa o código territorial e os registros locais disponíveis. A seleção fica na URL; não é necessário criar conta.</p>
        </main>
    </>;
}
