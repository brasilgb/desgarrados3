import { Link, router } from '@inertiajs/react';
import { MapPin, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { clearMunicipalityPreference, getLocationChoice, getMunicipalityPreference, saveLocationChoice, saveMunicipalityPreference, type MunicipalityPreference } from '@/lib/location-preference';

type State = { code: string; name: string };
type Municipality = MunicipalityPreference & { name: string };
type Catalog = { states: State[]; municipalities: Municipality[] };

export default function PortalLocation() {
    const [open, setOpen] = useState(false);
    const [firstVisit, setFirstVisit] = useState(false);
    const [catalog, setCatalog] = useState<Catalog>({ states: [], municipalities: [] });
    const [state, setState] = useState('');
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<Municipality | null>(null);
    const [current, setCurrent] = useState<Municipality | null>(null);
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const saved = getMunicipalityPreference();
        if (!getLocationChoice() && !saved) { setFirstVisit(true); setOpen(true); }
        else if (!getLocationChoice() && saved) saveLocationChoice('municipality');
        if (saved) void loadMunicipality(saved);
    }, []);

    useEffect(() => {
        if (!open) return;
        fetch(`/localidades/municipios${state ? `?state=${encodeURIComponent(state)}` : ''}`, { headers: { Accept: 'application/json' } })
            .then(response => response.ok ? response.json() as Promise<Catalog> : Promise.reject())
            .then(data => setCatalog(data)).catch(() => setMessage('Não foi possível carregar a lista de municípios.'));
    }, [open, state]);

    async function loadMunicipality(pref: MunicipalityPreference) {
        try {
            const response = await fetch(`/localidades/municipios?state=${encodeURIComponent(pref.state)}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json() as Catalog;
            setCurrent(data.municipalities.find(city => city.code === pref.code) ?? null);
        } catch { /* The saved code remains usable even when its display name is offline. */ }
    }

    const matches = useMemo(() => catalog.municipalities.filter(city => city.state === state && city.name.toLocaleLowerCase('pt-BR').includes(query.trim().toLocaleLowerCase('pt-BR'))).slice(0, 40), [catalog, query, state]);

    function openChooser() {
        setFirstVisit(false); setMessage(''); setSelected(null); setQuery('');
        const pref = getMunicipalityPreference(); setState(pref?.state ?? '');
        setOpen(true);
    }

    function defer() {
        saveLocationChoice('declined'); setOpen(false); setFirstVisit(false);
    }

    function resetChoice() {
        clearMunicipalityPreference();
        try { localStorage.removeItem('desgarrados:location:choice'); } catch { /* The current dialog remains usable without storage. */ }
        setCurrent(null); setSelected(null); setQuery(''); setState(''); setFirstVisit(true); setMessage('');
    }

    function choose(city: Municipality) {
        saveMunicipalityPreference({ code: city.code, state: city.state });
        saveLocationChoice('municipality'); setCurrent(city); setSelected(city); setOpen(false); setFirstVisit(false); setMessage('');
        window.dispatchEvent(new CustomEvent('portal-location-changed', { detail: { code: city.code, state: city.state } }));
        const path = window.location.pathname;
        const params = new URLSearchParams(window.location.search);
        const regionalPage = path === '/minha-regiao' || path === '/calendario-rural' || path === '/agenda-regional';
        const hasExplicitLocation = ['state', 'municipality', 'municipality_code', 'scope'].some(key => params.has(key));
        if (regionalPage && !hasExplicitLocation) {
            const queryParams: Record<string, string> = { ...Object.fromEntries(params.entries()), state: city.state, municipality_code: city.code };
            if (path === '/agenda-regional') queryParams.scope = 'municipality';
            if (path === '/calendario-rural') queryParams.scope = 'municipality';
            router.get(path, queryParams, { preserveScroll: true, preserveState: false });
        }
    }

    async function useGps() {
        setMessage('');
        if (!navigator.geolocation) { setMessage('Este navegador não oferece geolocalização. Escolhe o município na lista.'); return; }
        setBusy(true);
        navigator.geolocation.getCurrentPosition(async position => {
            try {
                const response = await fetch('/calendario-rural/localizacao', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' }, body: JSON.stringify({ latitude: position.coords.latitude, longitude: position.coords.longitude }) });
                const data = await response.json() as Municipality & { message?: string };
                if (!response.ok) throw new Error(data.message ?? 'Não foi possível identificar o município.');
                choose(data);
            } catch (error) { setMessage(error instanceof Error ? error.message : 'Falha ao identificar o município. Tenta escolher na lista.'); }
            finally { setBusy(false); }
        }, error => {
            setBusy(false);
            setMessage(error.code === error.PERMISSION_DENIED ? 'A permissão foi negada. Podes escolher o município na lista.' : error.code === error.TIMEOUT ? 'A localização demorou demais. Tenta novamente ou escolhe na lista.' : 'A localização está indisponível. Podes escolher o município na lista.');
        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
    }

    return <>
        <button type="button" onClick={openChooser} className="inline-flex min-h-9 max-w-full items-center gap-1.5 rounded-full border border-[#cfc4b2] px-3 py-1.5 text-xs font-semibold text-[#31453b] hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e]" aria-label={current ? `Localização: ${current.name}, ${current.state}. Alterar município` : 'Escolher município'}>
            <MapPin aria-hidden="true" className="size-4 shrink-0 text-[#bb623e]"/><span className="truncate">{current ? `${current.name}, ${current.state}` : 'Escolher região'}</span>
        </button>
        <Dialog open={open} onOpenChange={value => { if (!value && firstVisit) defer(); else setOpen(value); }}>
            <DialogContent className="max-h-[90dvh] max-w-lg overflow-y-auto border-[#ded5c5] bg-[#fbf8f1]">
                <DialogHeader><DialogTitle className="font-serif text-2xl text-[#31453b]">De que canto tu és?</DialogTitle><DialogDescription>Teu município ajuda a personalizar informações e serviços regionais. A localização é opcional e não precisa de cadastro.</DialogDescription></DialogHeader>
                <div className="grid gap-3">
                    <button type="button" disabled={busy} onClick={() => void useGps()} className="min-h-11 rounded-lg bg-[#31453b] px-4 py-2 text-sm font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e]">{busy ? 'Buscando localização…' : 'Usar minha localização'}</button>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="text-sm font-semibold text-[#31453b]">Estado<select value={state} onChange={event => { setState(event.target.value); setSelected(null); setQuery(''); }} className="mt-1 block h-11 w-full rounded-md border border-[#cfc4b2] bg-white px-3"><option value="">Escolhe a UF</option>{catalog.states.map(item => <option key={item.code} value={item.code}>{item.name} ({item.code})</option>)}</select></label>
                        <label className="text-sm font-semibold text-[#31453b]">Município<span className="relative mt-1 block"><Search aria-hidden="true" className="absolute left-3 top-3 size-4 text-stone-500"/><input value={selected?.name ?? query} onChange={event => { setSelected(null); setQuery(event.target.value); }} disabled={!state} role="combobox" aria-autocomplete="list" aria-expanded={Boolean(state && query && !selected)} aria-controls="municipality-options" placeholder="Buscar município" className="h-11 w-full rounded-md border border-[#cfc4b2] bg-white pl-9 pr-3"/></span></label>
                    </div>
                    {Boolean(state && query && !selected) && <ul id="municipality-options" role="listbox" aria-label="Municípios encontrados" className="max-h-40 overflow-y-auto rounded-lg border bg-white">{matches.length ? matches.map(city => <li key={city.code}><button type="button" role="option" aria-selected="false" onClick={() => { setSelected(city); setQuery(city.name); }} className="w-full px-3 py-2 text-left text-sm hover:bg-[#e8dfcf] focus-visible:bg-[#e8dfcf]">{city.name}</button></li>) : <li className="p-3 text-sm text-stone-600">Nenhum município encontrado.</li>}</ul>}
                    {selected && <button type="button" onClick={() => choose(selected)} className="min-h-11 rounded-lg bg-[#bb623e] px-4 py-2 text-sm font-semibold text-white">Usar {selected.name} ({selected.state})</button>}
                    {message && <p role="status" className="rounded-lg bg-[#e8dfcf] p-3 text-sm leading-5 text-[#31453b]">{message}</p>}
                    {firstVisit ? <button type="button" onClick={defer} className="min-h-10 rounded-lg px-4 py-2 text-sm font-semibold text-stone-700 underline">Agora não</button> : <button type="button" onClick={() => setOpen(false)} className="min-h-10 rounded-lg px-4 py-2 text-sm font-semibold text-stone-700 underline">Fechar</button>}
                    {!firstVisit && <><button type="button" onClick={resetChoice} className="text-center text-xs text-stone-500 underline">Redefinir escolha de localização</button><Link href="/politica-de-privacidade#cookies" className="text-center text-xs text-stone-500 underline">Preferências e privacidade</Link></>}
                </div>
            </DialogContent>
        </Dialog>
    </>;
}
