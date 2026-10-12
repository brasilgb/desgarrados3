import { Link, router } from '@inertiajs/react';
import PortalBreadcrumb from '@/components/portal-breadcrumb';
import { ArrowLeft, ArrowRight, CalendarDays, ExternalLink, MapPin } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AdminPagination from '@/components/admin-pagination';
import { clearMunicipalityPreference, getMunicipalityPreference, saveMunicipalityPreference } from '@/lib/location-preference';
import type { FormEvent } from 'react';
import PortalPageHero from '@/components/portal-page-hero';
import PortalSeo from '@/components/portal-seo';

type Entry = { id: number; activity: string; category: string; activity_type: string; frequency: string; region: string; state: string; municipality: string | null; starts_on: string; ends_on: string; reference_period: string | null; source_name: string; source_url: string; verified_at: string; notes: string | null };
type Source = { name: string; url: string; updated_at: string | null; description: string };
type ZarcRecord = { id: number; season_start: number; season_end: number; culture: string; state_code: string; municipality_code: string; municipality: string; dataset_key: string; source_row: Record<string, string> };
type Props = { month: string; entries: Entry[]; seasonalReferences: Entry[]; zarcRecords: { data: ZarcRecord[]; links: { url: string | null; label: string; active: boolean }[] }; zarcSyncedAt: string | null; zarcLastError: string | null; seasons: string[]; filters: Record<string, string>; states: string[]; municipalities: { code: string; name: string }[]; selectedMunicipality: { code: string; name: string; state: string } | null; geolocationAvailable: boolean; geolocationProvider: string | null; regions: string[]; categories: string[]; officialSources: Source[] };
const typeLabels: Record<string, string> = { agriculture: 'Agricultura', livestock: 'Pecuária', production: 'Produção' };
const months = Array.from({ length: 12 }, (_, index) => new Intl.DateTimeFormat('pt-BR', { month: 'long' }).format(new Date(2024, index, 1)));
const weekDays = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const formatDate = (date: string) => new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR');
const decennialLabel = (key: string) => {
    const number = Number(key.replace(/\D/g, ''));
    const month = Math.ceil(number / 3) - 1;
    const part = (number - 1) % 3;
    const starts = [1, 11, 21][part];
    const ends = [10, 20, new Date(2024, month + 1, 0).getDate()][part];
    const monthName = new Intl.DateTimeFormat('pt-BR', { month: 'short' }).format(new Date(2024, month, 1));
    return `${monthName} ${starts}–${ends}`;
};
const zarcFieldLabels: Record<string, string> = { Nome_cultura: 'Cultura', SafraIni: 'Início da safra', SafraFin: 'Fim da safra', Cod_Cultura: 'Código da cultura', Cod_Ciclo: 'Ciclo da cultivar', Cod_Solo: 'Classe de solo / água disponível', geocodigo: 'Código IBGE do município', UF: 'Estado', municipio: 'Município', Cod_Outros_Manejos: 'Tipo de manejo', Nome_Outros_Manejos: 'Nome do manejo', Cod_Clima: 'Código do clima', Nome_Clima: 'Clima', Cod_NM: 'Nível de manejo', Produtividade: 'Classificação por produtividade', Cod_Munic: 'Código municipal SICOR', Cod_Meso: 'Código IBGE da mesorregião', Cod_Micro: 'Código IBGE da microrregião', Portaria: 'Portaria do MAPA' };
const zarcCodes: Record<string, Record<string, string>> = {
    Cod_Ciclo: { '13': 'Perene', '19': 'Semiperene', '20': 'Grupo I', '21': 'Grupo II', '22': 'Grupo III', '24': 'Grupo IV', '25': 'Grupo V', '26': 'Grupo VI' },
    Cod_Solo: { '1': 'Arenoso', '2': 'Textura média', '3': 'Argiloso', '11': 'AD1', '12': 'AD2', '13': 'AD3', '14': 'AD4', '15': 'AD5', '16': 'AD6' },
    Cod_NM: { '1': 'NM1', '2': 'NM2', '3': 'NM3', '4': 'NM4' },
    Cod_Outros_Manejos: { '1': 'Sequeiro', '2': 'Irrigado', '3': 'Irrigado com controle de geada' },
    Cod_Clima: { '0': 'Não se aplica', '1': 'Alta disponibilidade de frio', '2': 'Média disponibilidade de frio', '3': 'Baixa disponibilidade de frio', '4': 'Semiárido', '5': 'Clima ameno', '6': 'Clima quente', '7': 'Clima tropical', '8': 'Clima subtropical ameno', '9': 'Clima subtropical frio', '11': 'Clima subtropical' },
};
const zarcValue = (key: string, value: string) => zarcCodes[key]?.[value] ? `${zarcCodes[key][value]} (código ${value})` : value || '—';
const isTechnicalZarcKey = (key: string) => key.startsWith('Cod_') || ['geocodigo', 'Cod_Munic', 'Cod_Meso', 'Cod_Micro'].includes(key);

export default function RuralCalendar({ month, entries, seasonalReferences, zarcRecords, zarcSyncedAt, zarcLastError, seasons, filters, states, municipalities, selectedMunicipality, geolocationAvailable, geolocationProvider, regions, categories, officialSources }: Props) {
    const [selected, setSelected] = useState<Entry | null>(null);
    const [selectedZarc, setSelectedZarc] = useState<ZarcRecord | null>(null);
    const [locationMessage, setLocationMessage] = useState('');
    const [locating, setLocating] = useState(false);
    const [year, monthIndex] = month.split('-').map(Number);
    const firstDay = new Date(year, monthIndex - 1, 1);
    const offset = (firstDay.getDay() + 6) % 7;
    const days = new Date(year, monthIndex, 0).getDate();
    const cells = [...Array(offset).fill(null), ...Array.from({ length: days }, (_, index) => index + 1)];
    const entriesByDay = useMemo(() => {
        const map = new Map<number, Entry[]>();
        const monthStart = `${month}-01`;
        const monthEnd = `${month}-${String(days).padStart(2, '0')}`;
        entries.forEach(entry => {
            const start = entry.starts_on < monthStart ? 1 : new Date(`${entry.starts_on}T12:00:00`).getDate();
            const end = entry.ends_on > monthEnd ? days : new Date(`${entry.ends_on}T12:00:00`).getDate();
            for (let day = start; day <= end; day++) map.set(day, [...(map.get(day) ?? []), entry]);
        });
        return map;
    }, [entries, month, days]);

    useEffect(() => {
        const query = new URLSearchParams(window.location.search);
        if (query.has('state') || query.has('municipality_code') || query.get('scope') === 'regional') return;
        const saved = getMunicipalityPreference();
        if (saved) router.get('/calendario-rural', { ...filters, state: saved.state, municipality_code: saved.code, scope: 'municipality', month }, { preserveScroll: true, preserveState: true, replace: true });
    // The saved location is consulted once on the initial page load.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const params = Object.fromEntries([...data.entries()].filter(([, value]) => value));
        if (params.scope === 'municipality' && typeof params.state === 'string' && typeof params.municipality_code === 'string') {
            saveMunicipalityPreference({ code: params.municipality_code, state: params.state });
        }
        router.get('/calendario-rural', { ...params, month }, { preserveScroll: true });
    }
    function changeScope(scope: string) {
        const params: Record<string, string> = { ...filters, month, scope };
        if (scope === 'regional') { delete params.state; delete params.municipality; delete params.municipality_code; }
        if (scope === 'state') { delete params.municipality; delete params.municipality_code; }
        router.get('/calendario-rural', params, { preserveScroll: true });
    }
    function resetMunicipality() {
        clearMunicipalityPreference();
        router.get('/calendario-rural', { month, state: 'RS', municipality_code: '4314902', scope: 'municipality' }, { preserveScroll: true });
    }
    function useMyLocation() {
        if (!navigator.geolocation) { resetMunicipality(); setLocationMessage('Este navegador não oferece geolocalização. Porto Alegre/RS foi selecionada.'); return; }
        setLocating(true);
        setLocationMessage('Aguardando a autorização do navegador. As coordenadas serão consultadas pelo serviço de geocodificação configurado e descartadas após a consulta.');
        navigator.geolocation.getCurrentPosition(async position => {
            try {
                const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
                const response = await fetch('/calendario-rural/localizacao', {
                    method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
                });
                if (!response.ok) throw new Error('Não foi possível identificar seu município com segurança.');
                const place = await response.json() as { code: string; state: string };
                saveMunicipalityPreference({ code: place.code, state: place.state });
                router.get('/calendario-rural', { ...filters, state: place.state, municipality_code: place.code, scope: 'municipality', month }, { preserveScroll: true });
            } catch (error) {
                resetMunicipality();
                setLocationMessage(error instanceof Error ? `${error.message} Porto Alegre/RS foi selecionada.` : 'Falha ao consultar a localização. Porto Alegre/RS foi selecionada.');
            } finally { setLocating(false); }
        }, () => { setLocating(false); resetMunicipality(); setLocationMessage('A localização não foi autorizada ou está indisponível. Porto Alegre/RS foi selecionada.'); }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
    }
    function moveMonth(delta: number) {
        const next = new Date(year, monthIndex - 1 + delta, 1);
        router.get('/calendario-rural', { ...filters, month: `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}` }, { preserveScroll: true });
    }

    return <>
        <PortalSeo title="Calendário rural regional" description="Registros sazonais de plantio, colheita e manejo por município, com safra, fonte oficial e data de verificação." />
        <PortalPageHero image="nossa-terra" icon={CalendarDays} eyebrow="Calendário rural" title="Atividades por lugar e período" intro="Consulte registros sazonais com município, safra, fonte e data de verificação. Produção contínua aparece identificada separadamente. Sem fonte oficial cadastrada, o calendário informa que não há dados disponíveis." /><PortalBreadcrumb items={[{ label: 'Portal', href: '/' }, { label: 'Nossa Terra', href: '/nossa-terra' }, { label: 'Calendário rural' }]} />
        <main className="mx-auto max-w-7xl space-y-6 px-5 py-9">
            <section aria-label="Localidade do calendário" className="rounded-2xl border border-[#ded5c5] bg-[#fbf8f1] p-4"><div className="flex flex-wrap items-center justify-between gap-3"><p className="text-sm font-semibold text-[#31453b]">Localidade selecionada: {selectedMunicipality ? `${selectedMunicipality.name}/${selectedMunicipality.state}` : filters.scope === 'state' ? `todo o estado ${filters.state}` : filters.scope === 'regional' ? 'consulta regional' : 'Porto Alegre/RS'}</p><div className="flex flex-wrap gap-2"><button type="button" onClick={useMyLocation} disabled={locating || !geolocationAvailable} title={!geolocationAvailable ? 'Serviço reverso ainda não configurado neste ambiente.' : undefined} className="portal-button-secondary">{locating ? 'Localizando…' : 'Usar minha localização'}</button><button type="button" onClick={resetMunicipality} className="portal-button-secondary">Restaurar Porto Alegre/RS</button></div></div><p className="mt-2 text-xs leading-5 text-stone-600">A localização só é solicitada quando você escolhe o botão e autoriza no navegador. {geolocationAvailable ? `As coordenadas serão enviadas a ${geolocationProvider ?? 'um serviço de geocodificação configurado'} apenas para encontrar município e UF; somente essa preferência será guardada neste navegador.` : 'A identificação por localização ainda não está configurada; escolha município manualmente.'} <Link href="/politica-de-privacidade" className="underline">Política de Privacidade</Link></p>{locationMessage && <p role="status" className="mt-2 text-sm text-stone-700">{locationMessage}</p>}</section>
            <form onSubmit={filter} className="grid min-w-0 gap-x-5 gap-y-6 rounded-2xl border border-[#ded5c5] bg-[#fbf8f1] p-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 [&>label]:min-w-0 [&>label]:leading-5 [&_input]:mt-2 [&_input]:min-h-11 [&_input]:min-w-0 [&_input]:px-3 [&_input]:py-2 [&_select]:mt-2 [&_select]:min-h-11 [&_select]:min-w-0 [&_select]:px-3 [&_select]:py-2">
                <label className="portal-label">Abrangência<select name="scope" value={filters.scope ?? 'municipality'} onChange={event => changeScope(event.target.value)} className="portal-field mt-1.5"><option value="municipality">Município</option><option value="state">Estado</option><option value="regional">Regional / todos</option></select></label>
                <label className="portal-label">Estado<select name="state" defaultValue={filters.state ?? (filters.scope === 'municipality' ? 'RS' : '')} onChange={event => { const { state: _state, municipality: _municipality, municipality_code: _code, ...rest } = filters; router.get('/calendario-rural', { ...rest, state: event.target.value, scope: event.target.value ? 'state' : 'regional', month }, { preserveScroll: true }); }} className="portal-field mt-1.5"><option value="">Todos</option>{states.map(state => <option key={state}>{state}</option>)}</select></label>
                <label className="portal-label">Município<select name="municipality_code" defaultValue={filters.municipality_code ?? ''} className="portal-field mt-1.5"><option value="">Todos</option>{municipalities.map(city => <option key={city.code} value={city.code}>{city.name}</option>)}</select></label>
                <label className="portal-label">Região<select name="region" defaultValue={filters.region ?? ''} className="portal-field mt-1.5"><option value="">Todas</option>{regions.map(region => <option key={region}>{region}</option>)}</select></label>
                <label className="portal-label">Cultura ou atividade<select name="category" defaultValue={filters.category ?? ''} className="portal-field mt-1.5"><option value="">Todas</option>{categories.map(category => <option key={category}>{category}</option>)}</select></label>
                <label className="portal-label">Tipo<select name="activity_type" defaultValue={filters.activity_type ?? ''} className="portal-field mt-1.5"><option value="">Todos</option>{Object.entries(typeLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
                <label className="portal-label">Safra ZARC<select name="season" defaultValue={filters.season ?? ''} className="portal-field mt-1.5"><option value="">Todas</option>{seasons.map(season => <option key={season}>{season}</option>)}</select></label>
                <label className="portal-label">Código do solo (MAPA)<input name="zarc_soil" inputMode="numeric" defaultValue={filters.zarc_soil ?? ''} className="portal-field mt-1.5" placeholder="Ex.: 13"/></label>
                <label className="portal-label">Código do ciclo (MAPA)<input name="zarc_cycle" inputMode="numeric" defaultValue={filters.zarc_cycle ?? ''} className="portal-field mt-1.5" placeholder="Ex.: 22"/></label>
                <label className="portal-label">Código de risco (MAPA)<input name="zarc_risk" inputMode="numeric" defaultValue={filters.zarc_risk ?? ''} className="portal-field mt-1.5" placeholder="Ex.: 2"/></label>
                <div className="flex items-end justify-end gap-3 border-t border-[#ded5c5] pt-4 sm:col-span-2 lg:col-span-3 xl:col-span-6"><button className="portal-button">Filtrar</button><Link href={`/calendario-rural?month=${month}`} className="portal-button-secondary">Limpar</Link></div>
            </form>
            <section aria-labelledby="seasonal-title" className="rounded-2xl border border-[#ded5c5] bg-[#fbf8f1] p-5">
                <p className="text-xs font-bold uppercase tracking-wider text-[#bb623e]">Referências verificáveis</p><h2 id="seasonal-title" className="mt-1 font-serif text-2xl font-bold text-[#31453b]">Safras, criações e atividades rurais</h2>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-stone-600">Os períodos abaixo vêm de registros publicados com fonte e data de verificação. Eles descrevem referências locais; não são previsões nem recomendações técnicas.</p>
                {seasonalReferences.length ? <div className="mt-4 grid gap-4 lg:grid-cols-3">{Object.entries(typeLabels).map(([type, label]) => {
                    const group = seasonalReferences.filter(entry => entry.activity_type === type);
                    return <section key={type} aria-label={label} className="rounded-xl border border-[#e3dacb] bg-white p-4"><h3 className="font-semibold text-[#31453b]">{label}</h3>{group.length ? <ul className="mt-3 space-y-3">{group.map(entry => <li key={entry.id} className="border-t border-[#eee9df] pt-3"><button type="button" onClick={() => setSelected(entry)} className="text-left font-semibold text-[#31453b] underline decoration-[#bb623e] underline-offset-2">{entry.activity}</button><p className="mt-1 text-xs leading-5 text-stone-600">{entry.category} · {entry.municipality ? `${entry.municipality}, ` : ''}{entry.state} · {formatDate(entry.starts_on)}–{formatDate(entry.ends_on)}</p><p className="text-xs text-stone-500">{entry.frequency === 'continuous' ? 'Produção contínua' : 'Período sazonal'} · {entry.source_name} · verificado {formatDate(entry.verified_at)}</p></li>)}</ul> : <p className="mt-3 text-sm text-stone-500">Sem referência cadastrada com fonte para este grupo.</p>}</section>;
                })}</div> : <p className="mt-4 rounded-xl bg-white p-4 text-sm text-stone-600">Ainda não há referências sazonais verificadas para os filtros atuais. Nenhuma data foi presumida.</p>}
            </section>
            <section aria-labelledby="month-title" className="overflow-hidden rounded-2xl border border-[#ded5c5] bg-white">
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-[#ded5c5] bg-[#fbf8f1] p-4"><button type="button" aria-label="Mês anterior" onClick={() => moveMonth(-1)} className="rounded-lg border p-2"><ArrowLeft className="h-4 w-4"/></button><h2 id="month-title" className="font-serif text-2xl font-bold capitalize text-[#31453b]">{months[monthIndex - 1]} {year}</h2><button type="button" aria-label="Próximo mês" onClick={() => moveMonth(1)} className="rounded-lg border p-2"><ArrowRight className="h-4 w-4"/></button></header>
                {entries.length ? <div className="overflow-x-auto"><div className="min-w-[740px]"><div className="grid grid-cols-7 border-b bg-[#f7f3eb]">{weekDays.map(day => <div key={day} className="p-3 text-center text-xs font-bold uppercase tracking-wider text-stone-500">{day}</div>)}</div><div className="grid grid-cols-7">{cells.map((day, index) => <div key={`${day ?? 'blank'}-${index}`} className={`min-h-32 border-b border-r border-[#eee9df] p-2 ${day ? '' : 'bg-stone-50'}`}>{day && <><span className="text-xs font-semibold text-stone-500">{day}</span><div className="mt-2 space-y-1">{(entriesByDay.get(day) ?? []).slice(0, 3).map(entry => <button type="button" key={entry.id} onClick={() => setSelected(entry)} className="block w-full truncate rounded-md bg-[#e8dfcf] px-2 py-1 text-left text-xs font-semibold text-[#31453b] hover:bg-[#d8cdb9]">{entry.activity}</button>)}{(entriesByDay.get(day)?.length ?? 0) > 3 && <span className="text-xs text-stone-500">+{entriesByDay.get(day)!.length - 3} mais</span>}</div></>}</div>)}</div></div></div> : <div className="p-8 md:p-12"><div className="mx-auto max-w-2xl text-center"><CalendarDays className="mx-auto h-9 w-9 text-[#bb623e]"/><h3 className="mt-4 font-serif text-2xl font-bold">Ainda não há períodos cadastrados para esta consulta</h3><p className="mt-2 text-sm leading-6 text-stone-600">Não exibimos datas genéricas como recomendações. A redação deve cadastrar períodos com safra, recorte municipal/regional, fonte e data de verificação.</p></div></div>}
                {entries.length > 0 && <div className="divide-y border-t border-[#ded5c5]">{entries.map(entry => <button type="button" key={entry.id} onClick={() => setSelected(entry)} className="grid w-full gap-2 p-4 text-left hover:bg-[#fbf8f1] sm:grid-cols-[1fr_auto] sm:items-center"><span><strong className="block text-[#31453b]">{entry.activity}</strong><span className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-600"><span>{formatDate(entry.starts_on)} – {formatDate(entry.ends_on)}</span><span>{entry.category} · {typeLabels[entry.activity_type] ?? entry.activity_type}</span><span className="flex items-center gap-1"><MapPin className="h-3 w-3"/>{entry.municipality ? `${entry.municipality}, ` : ''}{entry.state} · {entry.region}</span></span></span><span className="text-xs text-stone-500">{entry.reference_period ?? 'Sem safra informada'} · Verificado {formatDate(entry.verified_at)}</span></button>)}</div>}
            </section>
            <section aria-labelledby="zarc-title" className="overflow-hidden rounded-2xl border border-[#ded5c5] bg-white"><header className="border-b bg-[#fbf8f1] p-5"><p className="text-xs font-bold uppercase tracking-wider text-[#bb623e]">Dados oficiais importados</p><h2 id="zarc-title" className="mt-1 font-serif text-2xl font-bold text-[#31453b]">Tábua de Risco ZARC</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-stone-600">Registros originais do MAPA filtrados por município, cultura e safra. Os decêndios e critérios são exibidos como constam na fonte; confira a portaria, grupo/ciclo, solo e manejo antes de qualquer decisão. Isso não é recomendação agronômica.</p>{zarcSyncedAt && <p className="mt-2 text-xs text-stone-500">Dados sincronizados em {new Date(zarcSyncedAt).toLocaleString('pt-BR')}{zarcLastError && ' · fonte indisponível na última tentativa; exibimos a cópia anterior'}</p>}{zarcLastError && !zarcSyncedAt && <p role="status" className="mt-2 text-sm text-amber-800">A última atualização falhou e ainda não há uma cópia local disponível.</p>}</header>{zarcRecords.data.length ? <div className="divide-y">{zarcRecords.data.map(record => <button key={record.id} type="button" onClick={() => setSelectedZarc(record)} className="block w-full min-w-0 p-4 text-left hover:bg-[#fbf8f1] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#31453b]"><span className="flex flex-wrap items-center justify-between gap-2"><strong className="text-[#31453b]">{record.culture} · {record.municipality}/{record.state_code}</strong><span className="text-sm text-stone-600">Safra {record.season_start}/{record.season_end} · código IBGE {record.municipality_code}</span></span><span className="mt-1 block text-xs text-stone-500">Tábua de Risco do MAPA · {record.dataset_key} · abrir resumo e decêndios</span></button>)}</div> : <p className="p-6 text-sm text-stone-600">{zarcLastError ? 'A fonte oficial não pôde ser atualizada e não há registros locais para estes filtros.' : zarcSyncedAt ? 'A cópia local sincronizada não contém registros para os filtros escolhidos.' : 'Ainda não há sincronização ZARC disponível neste portal.'} <a className="underline" href="https://dados.agricultura.gov.br/dataset/tabua-de-risco-zoneamento-agricola-de-risco-climatico" target="_blank" rel="noreferrer">Consulte o catálogo do MAPA.</a></p>}<AdminPagination links={zarcRecords.links} label="Paginação dos registros ZARC"/></section>
            <section aria-labelledby="sources-title" className="rounded-2xl bg-[#e8dfcf] p-5"><h2 id="sources-title" className="font-serif text-xl font-bold">Fontes oficiais para consulta</h2><div className="mt-4 grid gap-3 md:grid-cols-2">{officialSources.map(source => <a key={source.url} href={source.url} target="_blank" rel="noreferrer" className="rounded-xl bg-[#fbf8f1] p-4 hover:shadow-sm"><span className="flex items-center justify-between gap-2 font-semibold text-[#31453b]">{source.name}<ExternalLink className="h-4 w-4 shrink-0"/></span><span className="mt-2 block text-sm leading-5 text-stone-600">{source.description}</span>{source.updated_at && <span className="mt-2 block text-xs text-stone-500">Página atualizada em {formatDate(source.updated_at)}</span>}</a>)}</div><p className="mt-4 text-xs leading-5 text-stone-600">O ZARC publica janelas de risco climático por safra e conforme município, solo e ciclo da cultivar. O calendário não converte essas janelas em recomendação universal.</p></section>
        </main>
        <Dialog open={Boolean(selected)} onOpenChange={open => !open && setSelected(null)}><DialogContent className="max-h-[90dvh] overflow-y-auto bg-background text-foreground sm:max-w-xl"><DialogHeader><p className="text-xs font-bold uppercase tracking-widest text-primary">{selected?.category} · {selected && typeLabels[selected.activity_type]}</p><DialogTitle className="font-serif text-2xl text-foreground">{selected?.activity}</DialogTitle><DialogDescription>{selected?.frequency === 'continuous' ? 'Sistema produtivo contínuo no período informado.' : 'Atividade sazonal no período da referência.'}</DialogDescription></DialogHeader>{selected && <div className="space-y-3 text-sm text-foreground"><p><strong>Período:</strong> {formatDate(selected.starts_on)} a {formatDate(selected.ends_on)} · {selected.reference_period ?? 'Sem safra informada'}</p><p><strong>Local:</strong> {selected.municipality ? `${selected.municipality}, ` : ''}{selected.state} · {selected.region}</p>{selected.notes && <p className="leading-6">{selected.notes}</p>}<p><strong>Fonte:</strong> {selected.source_name} · verificada em {formatDate(selected.verified_at)}</p><a href={selected.source_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 font-semibold text-primary underline">Abrir referência oficial <ExternalLink className="h-4 w-4"/></a></div>}</DialogContent></Dialog>
        <Dialog open={Boolean(selectedZarc)} onOpenChange={open => !open && setSelectedZarc(null)}><DialogContent className="max-h-[90dvh] w-[calc(100vw-2rem)] overflow-x-hidden overflow-y-auto bg-[#fbf8f1] text-[#28271f] sm:max-w-4xl"><DialogHeader className="min-w-0"><p className="text-xs font-bold uppercase tracking-widest text-[#bb623e]">MAPA · ZARC · {selectedZarc?.dataset_key}</p><DialogTitle className="font-serif text-2xl text-[#31453b]">{selectedZarc?.culture} · {selectedZarc?.municipality}/{selectedZarc?.state_code} · safra {selectedZarc?.season_start}/{selectedZarc?.season_end}</DialogTitle><DialogDescription className="text-[#625f55]">Informações da tábua oficial. Os decêndios preservam os valores originais publicados pelo MAPA.</DialogDescription></DialogHeader>{selectedZarc && <div className="min-w-0 max-w-full space-y-4"><div className="grid w-full min-w-0 max-w-full grid-cols-1 gap-2 sm:grid-cols-2">{Object.entries(selectedZarc.source_row).filter(([key]) => !/^dec\s?\d+$/i.test(key) && !isTechnicalZarcKey(key)).map(([key,value]) => <div key={key} className="min-w-0 break-words rounded-lg border border-[#ded5c5] bg-white p-3 text-sm"><span className="block break-words text-xs font-semibold text-[#625f55]">{zarcFieldLabels[key] ?? key}</span><span className="mt-1 block break-all font-medium text-[#28271f]">{zarcValue(key, value)}</span></div>)}</div>{Object.entries(selectedZarc.source_row).some(([key]) => isTechnicalZarcKey(key)) && <details className="overflow-hidden rounded-xl border border-[#ded5c5] bg-white"><summary className="cursor-pointer px-4 py-3 text-sm font-semibold text-[#31453b] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#bb623e]">Identificadores técnicos e códigos do MAPA</summary><div className="grid min-w-0 gap-2 border-t border-[#ded5c5] bg-[#fbf8f1] p-4 sm:grid-cols-2">{Object.entries(selectedZarc.source_row).filter(([key]) => isTechnicalZarcKey(key)).map(([key,value]) => <div key={key} className="min-w-0 break-words rounded-lg border border-[#ded5c5] bg-white p-3 text-sm"><span className="block break-words text-xs font-semibold text-[#625f55]">{zarcFieldLabels[key] ?? key}</span><span className="mt-1 block break-all font-medium text-[#28271f]">{zarcValue(key, value)}</span></div>)}</div></details>}<div className="max-w-full overflow-x-auto"><table className="w-full min-w-[850px] border-collapse bg-white text-center text-xs text-[#28271f]"><caption className="mb-2 text-left text-sm font-semibold text-[#28271f]">Períodos de plantio e valores originais do arquivo MAPA</caption><thead><tr>{Object.entries(selectedZarc.source_row).filter(([key]) => /^dec\s?\d+$/i.test(key)).map(([key]) => <th key={key} className="border border-[#ded5c5] bg-[#f5f0e6] p-2 text-[#28271f]"><span className="block">{decennialLabel(key)}</span><span className="mt-1 block font-normal text-[#625f55]">{key}</span></th>)}</tr></thead><tbody><tr>{Object.entries(selectedZarc.source_row).filter(([key]) => /^dec\s?\d+$/i.test(key)).map(([key,value]) => <td key={key} className="border border-[#ded5c5] p-2 text-[#28271f]">{value || '—'}</td>)}</tr></tbody></table></div><a className="inline-flex items-center gap-2 text-sm font-semibold underline" href="https://dados.agricultura.gov.br/dataset/tabua-de-risco-zoneamento-agricola-de-risco-climatico" target="_blank" rel="noreferrer">Conjunto oficial no MAPA <ExternalLink className="h-4 w-4"/></a></div>}</DialogContent></Dialog>
    </>;
}
