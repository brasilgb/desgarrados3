import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, MouseEvent } from 'react';
import { useEffect, useRef, useState } from 'react';

type Publication = {
    id: number; title: string; excerpt: string | null; body: string; type: string;
    category_id: number | null; credit: string | null; origin_state: string | null;
    origin_city: string | null; origin_scope: string; municipality_code: number | null; source_references: { name: string; url: string; note: string | null }[] | null; cover_path: string | null; status: string;
    published_at: string | null; seo_title: string | null; seo_description: string | null; is_featured: boolean; series_id: number | null; series_position: number | null; lock_version: number; updated_at: string;
};
type MediaItem = { id: number; path: string; preview_url: string; original_name: string; alt_text: string | null; credit: string | null };
type PlaceOption = { code: string | number; name: string };
type SourceReference = { name: string; url: string; note: string };
type Props = { publication: Publication | null; categories: { id: number; name: string }[]; categoryIds?: number[]; media: MediaItem[]; collections: { id: number; name: string }[]; collectionIds?: number[]; series: { id: number; title: string }[]; states: PlaceOption[]; municipalities: PlaceOption[] };
const types = [
    ['historia', 'História de vida'], ['causo', 'Causo'], ['anedota', 'Anedota e humor'],
    ['poema', 'Poema'], ['literatura', 'Literatura'], ['musica', 'Música e patrimônio'], ['tradição', 'Tradição'],
];
const prompts: Record<string, string> = {
    historia: 'Conte a trajetória, as lembranças e os lugares que fazem parte desta história…',
    causo: 'Apresente o cenário e conte o causo com o ritmo de uma boa conversa…',
    anedota: 'Prepare o leitor para a surpresa e o humor desta anedota…',
    poema: 'Escreva seus versos. As quebras de linha serão mantidas…',
    literatura: 'Apresente a obra e seu contexto. Inclua somente trechos autorizados…',
    musica: 'Conte sobre a música, o artista e seu valor cultural. Não inclua letras sem autorização…',
    tradição: 'Registre a tradição, os saberes e as pessoas que a mantêm viva…',
};

export default function FormPage({ publication, categories, categoryIds = [], media, collections, collectionIds = [], series, states, municipalities: initialMunicipalities }: Props) {
    const editor = useRef<HTMLDivElement>(null);
    const savedRange = useRef<Range | null>(null);
    const [showMedia, setShowMedia] = useState(false);
    const [previewing, setPreviewing] = useState(false);
    const [municipalities, setMunicipalities] = useState<PlaceOption[]>(initialMunicipalities);
    const { url } = usePage();
    const initialData = {
        title: publication?.title ?? '', excerpt: publication?.excerpt ?? '', body: publication?.body ?? '',
        type: publication?.type ?? 'historia', category_id: publication?.category_id?.toString() ?? '', category_ids: categoryIds.length ? categoryIds : publication?.category_id ? [publication.category_id] : [],
        credit: publication?.credit ?? '', origin_state: publication?.origin_state ?? '',
        origin_city: publication?.origin_city ?? '', origin_scope: publication?.origin_scope ?? (publication?.origin_city ? 'municipality' : publication?.origin_state ? 'state' : 'regional'),
        municipality_code: publication?.municipality_code?.toString() ?? '', source_references: publication?.source_references ?? [], cover_path: publication?.cover_path ?? '',
        status: publication?.status ?? 'draft', published_at: publication?.published_at?.slice(0, 16) ?? '',
        seo_title: publication?.seo_title ?? '', seo_description: publication?.seo_description ?? '', is_featured: publication?.is_featured ?? false,
        collection_ids: collectionIds, series_id: publication?.series_id?.toString() ?? '', series_position: publication?.series_position?.toString() ?? '',
    };
    const [savedSnapshot, setSavedSnapshot] = useState(() => JSON.stringify(initialData));
    const form = useForm(initialData);
    useEffect(() => {
        const state = form.data.origin_state;
        if (!state) { setMunicipalities([]); return; }
        const controller = new AbortController();
        fetch(`/admin/publications/municipalities?state=${encodeURIComponent(state)}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(response => response.ok ? response.json() as Promise<PlaceOption[]> : [])
            .then(items => setMunicipalities(items))
            .catch(error => { if (error.name !== 'AbortError') setMunicipalities([]); });
        return () => controller.abort();
    }, [form.data.origin_state]);
    const hasUnsavedChanges = JSON.stringify(form.data) !== savedSnapshot;
    const dataJson = JSON.stringify(form.data);
    const recoveryKey = `desgarrados-editorial-draft-${publication?.id ?? 'new'}`;
    const recoveryEnabled = !publication || publication.status === 'draft';
    const [localSavedAt, setLocalSavedAt] = useState<number | null>(null);
    const [autosaveState, setAutosaveState] = useState<'idle' | 'pending' | 'saving' | 'saved' | 'error' | 'conflict'>('idle');
    const [serverSavedAt, setServerSavedAt] = useState<string | null>(null);
    const serverVersion = useRef(publication?.lock_version ?? 0);
    const serverSnapshot = useRef(JSON.stringify(initialData));
    const latestData = useRef(form.data);
    const requestRunning = useRef(false);
    const requestQueued = useRef(false);
    const autosaveBlocked = useRef(false);
    const autosaveRef = useRef<() => void>(() => undefined);
    latestData.current = form.data;
    useEffect(() => {
        if (editor.current) editor.current.innerHTML = form.data.body;
    }, [publication?.id]);
    useEffect(() => {
        if (!recoveryEnabled) return;
        try {
            const stored = localStorage.getItem(recoveryKey);
            if (!stored) return;
            const recovery = JSON.parse(stored) as { savedAt?: number; data?: typeof initialData };
            const isNewerThanDatabase = !publication || (recovery.savedAt ?? 0) > new Date(publication.updated_at).getTime();
            if (recovery.data && isNewerThanDatabase && JSON.stringify(recovery.data) !== JSON.stringify(initialData) && window.confirm('Foi encontrada uma cópia recente deste rascunho. Deseja recuperar as alterações?')) {
                form.setData(recovery.data);
                setLocalSavedAt(recovery.savedAt ?? Date.now());
            }
        } catch {
            localStorage.removeItem(recoveryKey);
        }
    }, [publication?.id]);
    useEffect(() => {
        if (!recoveryEnabled || !hasUnsavedChanges) return;
        const timer = window.setTimeout(() => {
            try {
                localStorage.setItem(recoveryKey, JSON.stringify({ savedAt: Date.now(), data: form.data }));
                if (!publication) setSavedSnapshot(dataJson);
                setLocalSavedAt(Date.now());
            } catch {
                setAutosaveState('error');
            }
        }, 500);
        return () => window.clearTimeout(timer);
    }, [dataJson, recoveryEnabled, recoveryKey, hasUnsavedChanges]);
    autosaveRef.current = async () => {
        if (!publication || autosaveBlocked.current) return;
        if (requestRunning.current) {
            requestQueued.current = true;
            return;
        }
        if (serverSnapshot.current === JSON.stringify(latestData.current)) return;
        requestRunning.current = true;
        requestQueued.current = false;
        const snapshot = latestData.current;
        setAutosaveState('saving');
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            const response = await fetch(`/admin/publications/${publication.id}/autosave`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ ...snapshot, status: 'draft', lock_version: serverVersion.current }),
            });
            if (response.status === 409) {
                autosaveBlocked.current = true;
                setAutosaveState('conflict');
            } else if (!response.ok) {
                setAutosaveState('error');
            } else {
                const result = await response.json() as { lock_version: number; updated_at: string };
                serverVersion.current = result.lock_version;
                serverSnapshot.current = JSON.stringify(snapshot);
                setServerSavedAt(result.updated_at);
                setSavedSnapshot(JSON.stringify(snapshot));
                setAutosaveState('saved');
            }
        } catch {
            setAutosaveState('error');
        } finally {
            requestRunning.current = false;
            if (!autosaveBlocked.current && (requestQueued.current || serverSnapshot.current !== JSON.stringify(latestData.current))) {
                window.setTimeout(() => autosaveRef.current(), 700);
            }
        }
    };
    useEffect(() => {
        if (!publication || publication.status !== 'draft' || dataJson === serverSnapshot.current) return;
        setAutosaveState('pending');
        const timer = window.setTimeout(() => autosaveRef.current(), 1200);
        return () => window.clearTimeout(timer);
    }, [publication?.id, publication?.status, dataJson]);
    useEffect(() => {
        if (!hasUnsavedChanges) return;
        const warnBeforeLeave = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', warnBeforeLeave);
        return () => window.removeEventListener('beforeunload', warnBeforeLeave);
    }, [hasUnsavedChanges]);
    const field = 'mt-1 w-full rounded-lg border border-input bg-card px-3 py-2';

    function rememberSelection() {
        const selection = window.getSelection();
        if (selection?.rangeCount && editor.current?.contains(selection.anchorNode)) savedRange.current = selection.getRangeAt(0).cloneRange();
    }
    function command(name: string, value?: string) {
        editor.current?.focus();
        document.execCommand(name, false, value);
        if (editor.current) form.setData('body', editor.current.innerHTML);
    }
    function addLink() {
        const href = window.prompt('Endereço do link (https://…)');
        if (href) command('createLink', href);
    }
    function addImage(item: MediaItem, event: MouseEvent<HTMLButtonElement>) {
        event.preventDefault();
        const target = editor.current;
        if (!target) return;
        target.focus();
        const selection = window.getSelection();
        if (savedRange.current && selection) {
            selection.removeAllRanges();
            selection.addRange(savedRange.current);
        }
        const range = selection?.rangeCount ? selection.getRangeAt(0) : null;
        const figure = document.createElement('figure');
        const image = document.createElement('img');
        image.src = item.preview_url;
        image.alt = item.alt_text ?? item.original_name;
        image.loading = 'lazy';
        figure.append(image);
        if (item.credit) {
            const caption = document.createElement('figcaption');
            caption.textContent = `Crédito: ${item.credit}`;
            figure.append(caption);
        }
        if (range && target.contains(range.commonAncestorContainer)) {
            range.deleteContents();
            range.insertNode(figure);
        } else target.append(figure);
        form.setData('body', target.innerHTML);
        setShowMedia(false);
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        if (editor.current) form.setData('body', editor.current.innerHTML);
        const values = { ...form.data, body: editor.current?.innerHTML ?? form.data.body };
        form.transform(() => values);
        const returnQuery = new URLSearchParams(url.split('?')[1] ?? '').toString();
        const editUrl = `/admin/publications/${publication?.id ?? ''}${returnQuery ? `?${returnQuery}` : ''}`;
        if (publication) form.put(editUrl, { onSuccess: () => localStorage.removeItem(recoveryKey) });
        else form.post('/admin/publications', { onSuccess: () => localStorage.removeItem(recoveryKey) });
    }
    function preview() {
        if (editor.current) form.setData('body', editor.current.innerHTML);
        setPreviewing(true);
        router.post('/admin/publications/preview', { ...form.data, body: editor.current?.innerHTML ?? form.data.body }, { onFinish: () => setPreviewing(false) });
    }
    const toolbar = [
        ['bold', 'Negrito'], ['italic', 'Itálico'], ['underline', 'Sublinhar'],
        ['formatBlock:h2', 'Título'], ['formatBlock:blockquote', 'Citação'],
        ['insertUnorderedList', 'Lista •'], ['insertOrderedList', 'Lista numerada'],
    ];
    const plainBody = form.data.body.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/gi, ' ').trim();
    const wordCount = plainBody ? plainBody.split(/\s+/).filter(Boolean).length : 0;
    const readingMinutes = Math.max(1, Math.ceil(wordCount / 200));
    const filterQuery = new URLSearchParams(url.split('?')[1] ?? '').toString();
    const publicationListUrl = `/admin/publications${filterQuery ? `?${filterQuery}` : ''}`;

    return <>
        <Head title={publication ? 'Editar publicação' : 'Nova publicação'} />
        <div className="w-full min-w-0">
            <div className="flex flex-wrap items-center justify-between gap-3"><Link href={publicationListUrl} className="text-sm text-muted-foreground">← Publicações</Link>{publication && <Link href={`/admin/publications/${publication.id}/revisions${filterQuery ? `?${filterQuery}` : ''}`} className="text-sm font-semibold text-primary underline">Histórico de revisões</Link>}</div>
            <h1 className="my-5 font-serif text-3xl font-bold">{publication ? 'Editar publicação' : 'Nova publicação'}</h1>
            <form onSubmit={submit} className="space-y-6 rounded-2xl border bg-card p-5 shadow-sm md:p-8">
                <label className="block text-sm font-semibold">Título
                    <input className={field} value={form.data.title} onChange={e => form.setData('title', e.target.value)} required maxLength={180} />
                    {form.errors.title && <small className="text-destructive">{form.errors.title}</small>}
                </label>
                <label className="block text-sm font-semibold">Resumo de abertura
                    <textarea className={field} value={form.data.excerpt} onChange={e => form.setData('excerpt', e.target.value)} rows={2} maxLength={500} />
                </label>
                <section aria-label="Editor de texto" className="overflow-hidden rounded-xl border border-input">
                    <div className="flex flex-wrap gap-1 border-b bg-muted p-2">
                        {toolbar.map(([action, label]) => {
                            const [name, value] = action.split(':');
                            return <button key={action} type="button" onMouseDown={e => e.preventDefault()} onClick={() => command(name, value)} className="rounded-lg border bg-card px-3 py-2 text-xs font-semibold hover:bg-muted">{label}</button>;
                        })}
                        <button type="button" onMouseDown={e => e.preventDefault()} onClick={addLink} className="rounded-lg border bg-card px-3 py-2 text-xs font-semibold hover:bg-muted">Inserir link</button>
                        <button type="button" onMouseDown={rememberSelection} onClick={() => setShowMedia(!showMedia)} className="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground">Inserir imagem</button>
                    </div>
                    <p className="border-b bg-card px-3 py-2 text-xs text-muted-foreground">{wordCount} palavras · cerca de {readingMinutes} min de leitura</p>
                    {showMedia && <div className="grid max-h-64 grid-cols-2 gap-3 overflow-y-auto border-b bg-primary/5 p-3 sm:grid-cols-3 md:grid-cols-4">
                        {media.map(item => <button key={item.id} type="button" onMouseDown={e => e.preventDefault()} onClick={e => addImage(item, e)} className="overflow-hidden rounded-lg border bg-card text-left">
                            <img src={item.preview_url} alt={item.alt_text ?? item.original_name} className="aspect-[4/3] w-full object-cover" />
                            <span className="block truncate p-2 text-xs">{item.original_name}</span>
                        </button>)}
                        {media.length === 0 && <p className="col-span-full p-4 text-sm text-muted-foreground">A biblioteca está vazia. <Link href="/admin/media" className="underline">Envie uma imagem</Link> para inseri-la aqui.</p>}
                    </div>}
                    <div ref={editor} contentEditable suppressContentEditableWarning onInput={e => form.setData('body', e.currentTarget.innerHTML)} onKeyUp={rememberSelection} onMouseUp={rememberSelection} role="textbox" aria-label="Texto da publicação" aria-multiline="true" className="editorial-body min-h-72 max-w-none px-5 py-4 text-base leading-8 outline-none focus:ring-2 focus:ring-emerald-800" data-placeholder={prompts[form.data.type]} />
                </section>
                <p className="-mt-4 text-xs text-muted-foreground">Formate o texto pelos botões. Imagens inseridas precisam estar na biblioteca; confira os créditos antes de publicar.</p>
                {form.errors.body && <small className="text-destructive">{form.errors.body}</small>}
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="text-sm font-semibold">Caderno
                        <select className={field} value={form.data.type} onChange={e => form.setData('type', e.target.value)}>{types.map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select>
                        <span className="mt-1 block font-normal text-muted-foreground">{prompts[form.data.type]}</span>
                    </label>
                    <fieldset className="rounded-xl border border-border p-4"><legend className="px-1 text-sm font-semibold">Assuntos</legend><p className="text-xs font-normal text-muted-foreground">Marque todos os temas relacionados.</p><div className="mt-3 grid gap-2 sm:grid-cols-2">{categories.map(category => <label key={category.id} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.category_ids.includes(category.id)} onChange={event => { const ids = event.target.checked ? [...form.data.category_ids, category.id] : form.data.category_ids.filter(id => id !== category.id); form.setData(data => ({ ...data, category_ids: ids, category_id: ids[0]?.toString() ?? '' })); }} />{category.name}</label>)}{categories.length === 0 && <Link href="/admin/categories" className="text-sm underline">Cadastre um assunto</Link>}</div></fieldset>
                    <label className="text-sm font-semibold">Crédito e autoria
                        <input className={field} value={form.data.credit} onChange={e => form.setData('credit', e.target.value)} />
                        <span className="mt-1 block font-normal text-muted-foreground">Informe autor, fotógrafo, compositor ou titular dos direitos. Publique somente materiais próprios ou autorizados.</span>
                    </label>
                    <label className="text-sm font-semibold">Imagem de capa
                        <select className={field} value={form.data.cover_path} onChange={e => form.setData('cover_path', e.target.value)}><option value="">Sem capa</option>{media.map(item => <option value={item.path} key={item.id}>{item.original_name}{item.credit ? ` · ${item.credit}` : ''}</option>)}</select>
                    </label>
                    <fieldset className="space-y-3 rounded-xl border border-border p-4 md:col-span-2"><legend className="px-1 text-sm font-semibold">Abrangência cultural</legend><p className="text-xs text-muted-foreground">A localização é opcional. Escolha município ou estado quando houver vínculo específico; use regional para conteúdos de várias localidades ou do Sul.</p>
                        <div className="grid gap-3 sm:grid-cols-3"><label className="text-sm font-semibold">Abrangência<select className={field} value={form.data.origin_scope} onChange={e => { const scope = e.target.value; form.setData(data => ({ ...data, origin_scope: scope, ...(scope === 'regional' ? { origin_state: '', origin_city: '', municipality_code: '' } : {}) })); }}>{[['municipality', 'Município'], ['state', 'Estado'], ['regional', 'Regional / várias localidades']].map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                        {form.data.origin_scope !== 'regional' && <label className="text-sm font-semibold">Estado<select className={field} value={form.data.origin_state} onChange={e => form.setData(data => ({ ...data, origin_state: e.target.value, municipality_code: '', origin_city: '' }))}><option value="">Selecione uma UF</option>{states.map(item => <option key={item.code} value={item.code}>{item.code} · {item.name}</option>)}</select></label>}
                        {form.data.origin_scope === 'municipality' && <label className="text-sm font-semibold">Município<select className={field} value={form.data.municipality_code} disabled={!form.data.origin_state} onChange={e => form.setData('municipality_code', e.target.value)}><option value="">Selecione um município</option>{municipalities.map(item => <option key={item.code} value={item.code}>{item.name}</option>)}</select>{form.errors.municipality_code && <small className="block text-destructive">{form.errors.municipality_code}</small>}</label>}</div>
                    </fieldset>
                    <label className="text-sm font-semibold">Estado editorial
                        <select className={field} value={form.data.status} onChange={e => form.setData('status', e.target.value)}>{[['draft', 'Rascunho'], ['scheduled', 'Agendado'], ['published', 'Publicado'], ['archived', 'Arquivado']].map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
                    </label>
                    {form.data.status === 'scheduled' && <label className="text-sm font-semibold">Data e hora de publicação<input type="datetime-local" className={field} value={form.data.published_at} onChange={e => form.setData('published_at', e.target.value)} required /></label>}
                </div>
                <fieldset className="space-y-3 border-t pt-5"><legend className="text-sm font-semibold">Fontes e referências</legend><p className="text-xs text-muted-foreground">Registre as fontes usadas para contextualizar fatos, datas, citações ou materiais de terceiros.</p>{form.data.source_references.map((source, index) => <div key={index} className="grid gap-3 rounded-xl border p-3 sm:grid-cols-2"><label className="text-sm font-semibold">Nome da fonte<input className={field} value={source.name} onChange={e => form.setData('source_references', form.data.source_references.map((item, i) => i === index ? { ...item, name: e.target.value } : item))} /></label><label className="text-sm font-semibold">URL<input type="url" className={field} value={source.url} onChange={e => form.setData('source_references', form.data.source_references.map((item, i) => i === index ? { ...item, url: e.target.value } : item))} /></label><label className="text-sm font-semibold sm:col-span-2">Nota opcional<input className={field} value={source.note ?? ''} onChange={e => form.setData('source_references', form.data.source_references.map((item, i) => i === index ? { ...item, note: e.target.value } : item))} /></label><button type="button" className="justify-self-start text-sm text-destructive underline" onClick={() => form.setData('source_references', form.data.source_references.filter((_, i) => i !== index))}>Remover referência</button>{form.errors[`source_references.${index}.url` as keyof typeof form.errors] && <small className="text-destructive">Informe uma URL válida.</small>}</div>)}<button type="button" onClick={() => form.setData('source_references', [...form.data.source_references, { name: '', url: '', note: '' }])} className="rounded-lg border px-3 py-2 text-sm font-semibold">Adicionar fonte +</button></fieldset>
                <section className="grid gap-5 border-t pt-5 md:grid-cols-2">
                    <fieldset><legend className="text-sm font-semibold">Coleções editoriais</legend><p className="mt-1 text-xs text-muted-foreground">Uma história pode estar em várias coleções.</p><div className="mt-3 grid gap-2">{collections.map(collection => <label key={collection.id} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.collection_ids.includes(collection.id)} onChange={event => form.setData('collection_ids', event.target.checked ? [...form.data.collection_ids, collection.id] : form.data.collection_ids.filter(id => id !== collection.id))} />{collection.name}</label>)}{collections.length === 0 && <Link href="/admin/collections" className="text-sm underline">Crie uma coleção</Link>}</div></fieldset>
                    <div><label className="block text-sm font-semibold">Série ou sequência<select className={field} value={form.data.series_id} onChange={event => form.setData('series_id', event.target.value)}><option value="">Publicação avulsa</option>{series.map(item => <option key={item.id} value={item.id}>{item.title}</option>)}</select></label>{form.data.series_id && <label className="mt-3 block text-sm font-semibold">Ordem do capítulo<input type="number" min="1" className={field} value={form.data.series_position} onChange={event => form.setData('series_position', event.target.value)} required /></label>}<Link href="/admin/series" className="mt-2 inline-block text-sm text-primary underline">Organizar séries</Link>{form.errors.series_position && <small className="block text-destructive">{form.errors.series_position}</small>}</div>
                </section>
                <details className="rounded-xl border p-4"><summary className="cursor-pointer font-semibold">SEO · título e descrição para buscadores</summary><div className="mt-4 space-y-4">
                    <label className="block text-sm font-semibold">Título SEO<input className={field} value={form.data.seo_title} maxLength={180} onChange={e => form.setData('seo_title', e.target.value)} placeholder={form.data.title || 'Título da página'} /></label>
                    <label className="block text-sm font-semibold">Descrição SEO<textarea className={field} value={form.data.seo_description} maxLength={300} rows={2} onChange={e => form.setData('seo_description', e.target.value)} placeholder={form.data.excerpt || 'Resumo para mecanismos de busca'} /></label>
                </div></details>
                <label className="flex items-start gap-3 rounded-xl border border-border bg-card p-4 text-sm"><input type="checkbox" checked={form.data.is_featured} onChange={event => form.setData('is_featured', event.target.checked)} className="mt-1"/><span><strong>Destacar na página inicial</strong><span className="mt-1 block text-muted-foreground">O destaque aparece apenas quando a publicação estiver no ar.</span></span></label>
                {Object.keys(form.errors).length > 0 && <p className="text-sm text-destructive">Revise os campos destacados antes de salvar.</p>}
                <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p role="status" aria-live="polite" className={`text-sm ${autosaveState === 'error' || autosaveState === 'conflict' ? 'font-semibold text-destructive' : hasUnsavedChanges ? 'font-semibold text-secondary-foreground' : 'text-muted-foreground'}`}>
                        {autosaveState === 'saving' ? 'Salvando rascunho…' : autosaveState === 'pending' ? 'Salvamento automático em instantes…' : autosaveState === 'conflict' ? 'Outro editor atualizou este rascunho. Recarregue antes de salvar.' : autosaveState === 'error' ? 'Não foi possível salvar agora. A cópia local será mantida.' : autosaveState === 'saved' ? `Rascunho salvo${serverSavedAt ? ` às ${new Date(serverSavedAt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}` : ''}.` : localSavedAt && !publication ? `Recuperação salva neste navegador às ${new Date(localSavedAt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}.` : hasUnsavedChanges ? 'Alterações ainda não salvas' : publication ? 'Todas as alterações foram salvas' : 'Novo rascunho'}
                    </p>
                    <div className="flex flex-col-reverse gap-3 sm:flex-row">
                    <button type="button" disabled={previewing} onClick={preview} className="rounded-lg border border-primary px-5 py-3 font-semibold text-primary disabled:opacity-60">{previewing ? 'Preparando prévia…' : 'Pré-visualizar publicação'}</button>
                    <button disabled={form.processing} className="admin-button">{form.processing ? 'Salvando…' : publication ? 'Salvar alterações' : 'Salvar publicação'}</button>
                    </div>
                </div>
            </form>
        </div>
    </>;
}
