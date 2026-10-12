import * as DialogPrimitive from '@radix-ui/react-dialog';
import { router } from '@inertiajs/react';
import { ArrowRight, BookOpen, CalendarDays, Calculator, Compass, CornerDownLeft, FileText, Library, LoaderCircle, Quote, Search, Sprout, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { toolCatalog, type ToolId } from '@/lib/regional-tools-catalog';
import { cultureLinks, landLinks } from '@/lib/public-navigation';

type Item = { title: string; section: string; description: string | null; url: string };
type Group = { key: string; label: string; items: Item[] };
type Suggestions = { query: string; groups: Group[]; hasMorePublications: boolean; tools: { enabled: ToolId[]; titles: Partial<Record<ToolId, string>> } };
type Status = 'idle' | 'loading' | 'done' | 'error';
type Option = Item & { id: string; group: string; icon: LucideIcon };

export const MIN_QUERY_LENGTH = 2;
const DEBOUNCE_MS = 250;

const icons: Record<string, LucideIcon> = { sections: Compass, publications: BookOpen, collections: Library, sayings: Quote, events: CalendarDays, guides: Sprout, pages: FileText, tools: Calculator, all: Search };

const sections: Item[] = [
    { title: 'Início', section: 'Portal', description: 'Destaques e publicações recentes', url: '/' },
    ...cultureLinks.map(link => ({ title: link.label, section: 'Cultura Gaúcha', description: null, url: link.href })),
    ...landLinks.map(link => ({ title: link.label, section: 'Nossa Terra', description: null, url: link.href })),
    { title: 'Compartilhe uma história', section: 'Participe', description: 'Envie um relato para a equipe editorial', url: '/contribua' },
    { title: 'Sobre o Desgarrados', section: 'Desgarrados', description: null, url: '/sobre' },
];
const quickAccess = ['/', '/cadernos/historias', '/cadernos/causos', '/cadernos/poesia', '/nossa-terra', '/calendario-rural', '/agenda-regional', '/contribua'];

const normalize = (value: string) => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR');

/** Ctrl/⌘ + K must not steal the shortcut from fields being edited or from another open dialog. */
function shouldIgnoreShortcut(event: KeyboardEvent): boolean {
    const target = event.target as HTMLElement | null;
    const editing = !!target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

    return event.defaultPrevented || event.isComposing || event.altKey || event.shiftKey || editing || !!document.querySelector('[role="dialog"][data-state="open"]');
}

export default function PortalSearch() {
    const [open, setOpen] = useState(false);
    const [shortcutLabel, setShortcutLabel] = useState('Ctrl K');
    const returnFocusRef = useRef<HTMLElement | null>(null);
    const desktopTriggerRef = useRef<HTMLButtonElement>(null);
    const mobileTriggerRef = useRef<HTMLButtonElement>(null);

    function openFrom(element: HTMLElement | null) {
        // Without a meaningful previous focus (shortcut pressed on the page), focus returns to the visible trigger.
        const visibleTrigger = desktopTriggerRef.current?.offsetParent ? desktopTriggerRef.current : mobileTriggerRef.current;
        returnFocusRef.current = element && element !== document.body ? element : visibleTrigger;
        setOpen(true);
    }

    useEffect(() => {
        const platform = (navigator as Navigator & { userAgentData?: { platform?: string } }).userAgentData?.platform ?? navigator.platform;
        if (/mac|iphone|ipad/i.test(platform)) setShortcutLabel('⌘ K');
    }, []);

    // One global listener for the whole public site, removed on unmount.
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key.toLowerCase() !== 'k' || !(event.metaKey || event.ctrlKey) || shouldIgnoreShortcut(event)) return;
            event.preventDefault();
            openFrom(document.activeElement instanceof HTMLElement ? document.activeElement : null);
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    return <>
        <button ref={desktopTriggerRef} type="button" onClick={event => openFrom(event.currentTarget)} aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K" className="hidden h-10 w-64 shrink-0 items-center gap-2 rounded-full border border-[#cfc4b2] bg-white pl-3 pr-1.5 text-left text-sm text-[#625f55] transition-colors hover:border-[#31453b] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e] md:inline-flex">
            <Search aria-hidden="true" className="h-4 w-4 shrink-0 text-[#625f55]" />
            <span className="min-w-0 flex-1 truncate">Buscar no Desgarrados...</span>
            <kbd aria-hidden="true" className="rounded-md border border-[#d9d0bf] bg-[#f5f0e6] px-1.5 py-0.5 font-sans text-[11px] font-semibold text-[#625f55]">{shortcutLabel}</kbd>
        </button>
        <button ref={mobileTriggerRef} type="button" onClick={event => openFrom(event.currentTarget)} aria-haspopup="dialog" aria-label="Buscar no Desgarrados" className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#cfc4b2] text-[#31453b] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bb623e] md:hidden">
            <Search aria-hidden="true" className="h-5 w-5" />
        </button>
        <DialogPrimitive.Root open={open} onOpenChange={setOpen}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-[#1b2922]/45 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    onCloseAutoFocus={event => { event.preventDefault(); returnFocusRef.current?.focus(); }}
                    className="fixed left-1/2 top-4 z-50 flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl -translate-x-1/2 flex-col overflow-hidden rounded-2xl border border-[#d9d0bf] bg-[#fbf8f1] text-[#28271f] shadow-2xl data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 sm:top-[12vh] sm:max-h-[min(38rem,76dvh)]"
                >
                    {open && <SearchPanel onNavigate={() => setOpen(false)} />}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    </>;
}

function SearchPanel({ onNavigate }: { onNavigate: () => void }) {
    const listboxId = useId();
    const [query, setQuery] = useState('');
    const [debounced, setDebounced] = useState('');
    const [status, setStatus] = useState<Status>('idle');
    const [result, setResult] = useState<Suggestions | null>(null);
    const [active, setActive] = useState(0);
    const cache = useRef(new Map<string, Suggestions>());
    const term = query.trim();

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(term), DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [term]);

    // One request per pause in typing; a newer term aborts the request still in flight.
    useEffect(() => {
        if (debounced.length < MIN_QUERY_LENGTH) {
            setStatus('idle');
            return;
        }
        const key = normalize(debounced);
        const cached = cache.current.get(key);
        if (cached) {
            setResult(cached);
            setStatus('done');
            return;
        }

        const controller = new AbortController();
        setStatus('loading');
        fetch(`/busca/sugestoes?q=${encodeURIComponent(debounced)}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(response => {
                if (!response.ok) throw new Error(String(response.status));
                return response.json() as Promise<Suggestions>;
            })
            .then(data => {
                cache.current.set(key, data);
                setResult(data);
                setStatus('done');
            })
            .catch(error => {
                if (!controller.signal.aborted) setStatus('error');
                return error;
            });

        return () => controller.abort();
    }, [debounced]);

    const groups = useMemo<Group[]>(() => {
        if (term.length < MIN_QUERY_LENGTH) {
            return [{ key: 'sections', label: 'Acessos rápidos', items: quickAccess.map(url => sections.find(item => item.url === url)!).filter(Boolean) }];
        }
        const needle = normalize(term);
        const matchedSections = sections.filter(item => normalize(`${item.title} ${item.section}`).includes(needle));
        const tools = result?.tools ?? { enabled: Object.keys(toolCatalog) as ToolId[], titles: {} };
        const matchedTools = tools.enabled
            .map(id => ({ ...toolCatalog[id], title: tools.titles[id] || toolCatalog[id].title }))
            .filter(tool => normalize(`${tool.title} ${tool.category} ${tool.description}`).includes(needle))
            .slice(0, 5)
            .map(tool => ({ title: tool.title, section: `Central Campeira · ${tool.category}`, description: tool.description, url: `/nossa-terra?ferramenta=${tool.id}` }));
        const fresh = result && normalize(result.query) === normalize(debounced) && debounced === term ? result.groups : [];

        return [
            ...(matchedSections.length ? [{ key: 'sections', label: 'Seções', items: matchedSections.slice(0, 5) }] : []),
            ...fresh,
            ...(matchedTools.length ? [{ key: 'tools', label: 'Ferramentas da Central Campeira', items: matchedTools }] : []),
            { key: 'all', label: 'Todos os resultados', items: [{ title: `Ver todas as publicações com “${term}”`, section: result?.hasMorePublications ? 'Há mais correspondências' : 'Página de resultados', description: null, url: `/?q=${encodeURIComponent(term)}` }] },
        ];
    }, [term, debounced, result]);

    // Flat list for arrow-key navigation; the same URL never appears twice.
    const options = useMemo<Option[]>(() => {
        const seen = new Set<string>();
        return groups.flatMap(group => group.items.filter(item => !seen.has(item.url) && seen.add(item.url)).map((item, index) => ({ ...item, id: `${listboxId}-${group.key}-${index}`, group: group.key, icon: icons[group.key] ?? Search })));
    }, [groups, listboxId]);

    useEffect(() => setActive(0), [term, result]);
    useEffect(() => {
        const element = options[active] && document.getElementById(options[active].id);
        element?.scrollIntoView({ block: 'nearest' });
    }, [active, options]);

    function go(option: Option | undefined) {
        if (!option) return;
        onNavigate();
        router.visit(option.url);
    }

    function onKeyDown(event: ReactKeyboardEvent<HTMLInputElement>) {
        if (!options.length) return;
        const moves: Record<string, () => number> = {
            ArrowDown: () => (active + 1) % options.length,
            ArrowUp: () => (active - 1 + options.length) % options.length,
            Home: () => 0,
            End: () => options.length - 1,
        };
        if (moves[event.key]) {
            event.preventDefault();
            setActive(moves[event.key]());
        } else if (event.key === 'Enter') {
            event.preventDefault();
            go(options[active]);
        }
    }

    const loading = status === 'loading' || (term.length >= MIN_QUERY_LENGTH && debounced !== term);
    const contentResults = options.filter(option => option.group !== 'all').length;
    const announcement = term.length < MIN_QUERY_LENGTH ? 'Digite ao menos 2 letras para buscar.'
        : loading ? 'Buscando…'
        : status === 'error' ? 'Não foi possível buscar agora. Tente novamente.'
        : contentResults === 0 ? `Nenhum resultado para “${term}”.`
        : `${contentResults} ${contentResults === 1 ? 'resultado encontrado' : 'resultados encontrados'}.`;

    return <>
        <DialogPrimitive.Title className="sr-only">Buscar no Desgarrados</DialogPrimitive.Title>
        <div className="flex items-center gap-3 border-b border-[#e6ddce] px-4">
            {loading ? <LoaderCircle aria-hidden="true" className="h-5 w-5 shrink-0 animate-spin text-[#bb623e]" /> : <Search aria-hidden="true" className="h-5 w-5 shrink-0 text-[#625f55]" />}
            <input
                autoFocus
                type="search"
                role="combobox"
                aria-label="Buscar no Desgarrados"
                aria-expanded="true"
                aria-controls={listboxId}
                aria-autocomplete="list"
                aria-activedescendant={options[active]?.id}
                value={query}
                maxLength={100}
                onChange={event => setQuery(event.target.value)}
                onKeyDown={onKeyDown}
                placeholder="Buscar no Desgarrados..."
                className="h-14 min-w-0 flex-1 bg-transparent text-base text-[#28271f] placeholder:text-[#777366] outline-none [&::-webkit-search-cancel-button]:hidden"
            />
            <DialogPrimitive.Close aria-label="Fechar busca" className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[#625f55] hover:bg-[#e8dfcf] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#bb623e]">
                <X aria-hidden="true" className="h-5 w-5" />
            </DialogPrimitive.Close>
        </div>
        <p role="status" aria-live="polite" className="sr-only">{announcement}</p>
        <div id={listboxId} role="listbox" aria-label="Resultados da busca" className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2">
            {groups.map(group => {
                const groupOptions = options.filter(option => option.group === group.key);
                if (!groupOptions.length) return null;
                return <div key={group.key} role="group" aria-labelledby={`${listboxId}-${group.key}-label`} className="py-1">
                    <p id={`${listboxId}-${group.key}-label`} className="px-3 pb-1 pt-2 text-xs font-bold uppercase tracking-wider text-[#8a4b30]">{group.label}</p>
                    {groupOptions.map(option => {
                        const index = options.indexOf(option);
                        const Icon = option.icon;
                        const selected = index === active;
                        return <div key={option.id} id={option.id} role="option" aria-selected={selected} onMouseMove={() => setActive(index)} onClick={() => go(option)} className={`flex cursor-pointer items-start gap-3 rounded-xl px-3 py-2.5 ${selected ? 'bg-[#31453b] text-white' : 'text-[#28271f]'}`}>
                            <Icon aria-hidden="true" className={`mt-0.5 h-4 w-4 shrink-0 ${selected ? 'text-[#e6d4b5]' : 'text-[#625f55]'}`} />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-semibold">{option.title}</span>
                                <span className={`block truncate text-xs ${selected ? 'text-[#dbe0d8]' : 'text-[#625f55]'}`}>{option.section}{option.description ? ` · ${option.description}` : ''}</span>
                            </span>
                            {selected && <CornerDownLeft aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0 text-[#e6d4b5]" />}
                        </div>;
                    })}
                </div>;
            })}
            {term.length >= MIN_QUERY_LENGTH && !loading && status !== 'error' && contentResults === 0 && <p className="px-3 py-6 text-center text-sm text-[#625f55]">Nenhum resultado para “{term}”. Tente outra palavra ou veja a página completa de resultados.</p>}
            {status === 'error' && <p className="px-3 py-6 text-center text-sm text-red-800">Não foi possível buscar agora. Tente novamente em instantes.</p>}
        </div>
        <footer className="hidden items-center justify-between gap-3 border-t border-[#e6ddce] px-4 py-2.5 text-xs text-[#625f55] sm:flex">
            <span><kbd className="font-sans font-semibold">↑</kbd> <kbd className="font-sans font-semibold">↓</kbd> navegar · <kbd className="font-sans font-semibold">Enter</kbd> abrir · <kbd className="font-sans font-semibold">Esc</kbd> fechar</span>
            <span className="inline-flex items-center gap-1">Busca em todo o portal <ArrowRight aria-hidden="true" className="h-3 w-3" /></span>
        </footer>
    </>;
}
