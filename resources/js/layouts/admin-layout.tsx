import { Head, Link, usePage } from '@inertiajs/react';
import { Archive, BookOpen, CalendarDays, CalendarRange, Calculator, ChevronDown, ExternalLink, FileText, FolderOpen, Home, Images, Library, LogOut, Mail, MessageSquareText, Moon, Palette, Radio, Settings, Sprout, Sun, Tags, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useEffect } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { Breadcrumbs } from '@/components/breadcrumbs';
import AppLogoIcon from '@/components/app-logo-icon';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sidebar, SidebarContent, SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarHeader, SidebarInset, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance, type Appearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';

type PageProps = {
    url: string;
    sidebarOpen?: boolean;
    auth: { user: { name: string; role: string } | null };
    capabilities?: { accessAdmin?: boolean; manageUsers?: boolean };
};
type Item = { href: string; label: string; icon: LucideIcon };
type Group = { label: string; items: Item[] };

const contentItems: Item[] = [
    { href: '/admin/publications', label: 'Publicações', icon: BookOpen },
    { href: '/admin/categories', label: 'Assuntos', icon: Tags },
    { href: '/admin/collections', label: 'Coleções', icon: Archive },
    { href: '/admin/series', label: 'Séries', icon: Library },
    { href: '/admin/sayings', label: 'Ditados e expressões', icon: MessageSquareText },
];

export default function AdminLayout({ children }: PropsWithChildren) {
    const page = usePage<PageProps>();
    const { auth, capabilities } = page.props;

    // Dialogs, menus and toasts render in portals on <body>, outside the provider wrapper.
    useEffect(() => {
        document.body.classList.add('admin-theme');

        return () => document.body.classList.remove('admin-theme');
    }, []);
    const currentPath = page.url.split('?')[0];
    const active = (href: string) => href === '/admin' ? currentPath === '/admin' || currentPath === '/admin/' : currentPath === href || currentPath.startsWith(`${href}/`);
    const groups: Group[] = capabilities?.accessAdmin ? [
        { label: 'Conteúdo editorial', items: contentItems },
        { label: 'Comunidade', items: [{ href: '/admin/submissions', label: 'Relatos e moderação', icon: MessageSquareText }] },
        { label: 'Central Campeira', items: [
            { href: '/admin/rural-calendar', label: 'Calendário rural', icon: CalendarDays },
            { href: '/admin/regional-events', label: 'Agenda regional', icon: CalendarRange },
            { href: '/admin/production-guides', label: 'Conteúdos produtivos', icon: Sprout },
            ...(capabilities.manageUsers ? [
                { href: '/admin/integrations', label: 'Fontes e integrações', icon: Radio },
                { href: '/admin/campeira', label: 'Ferramentas públicas', icon: Calculator },
            ] : []),
        ] },
        { label: 'Biblioteca', items: [{ href: '/admin/media', label: 'Imagens e arquivos', icon: Images }] },
        ...(capabilities.manageUsers ? [{ label: 'Administração', items: [
            { href: '/admin/users', label: 'Usuários e permissões', icon: Users },
            { href: '/admin/site-pages', label: 'Páginas institucionais', icon: FileText },
            { href: '/admin/contact-messages', label: 'Mensagens de contato', icon: Mail },
            { href: '/admin/settings/portal', label: 'Configurações do portal', icon: Settings },
        ] }] : []),
    ] : [];
    const selectedItem = groups.flatMap(group => group.items).find(item => active(item.href));
    const title = selectedItem?.label ?? (currentPath.startsWith('/settings/') ? ({
        '/settings/profile': 'Meu perfil',
        '/settings/security': 'Segurança da conta',
        '/settings/appearance': 'Aparência',
    } as Record<string, string>)[currentPath] ?? 'Minha conta' : 'Visão geral');
    const roleLabel = auth.user?.role === 'admin' ? 'Administrador' : auth.user?.role === 'editor' ? 'Editor' : 'Usuário';
    const initials = useInitials();
    const { appearance, updateAppearance } = useAppearance();

    const menuItem = (item: Item) => {
        const Icon = item.icon;
        return <SidebarMenuItem key={item.href}>
            <SidebarMenuButton asChild isActive={active(item.href)} tooltip={item.label}>
                <Link href={item.href} aria-current={active(item.href) ? 'page' : undefined}>
                    <Icon aria-hidden="true" />
                    <span>{item.label}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>;
    };

    return <SidebarProvider defaultOpen={page.props.sidebarOpen ?? true} className="admin-theme">
        <Head><meta head-key="robots" name="robots" content="noindex, nofollow" /></Head>
        <Sidebar collapsible="icon" variant="inset" className="admin-sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild size="lg" tooltip="Desgarrados">
                            <Link href={capabilities?.accessAdmin ? '/admin' : '/'} className="group-data-[collapsible=icon]:justify-center">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"><AppLogoIcon className="size-7 shrink-0 object-contain" /></span>
                                <span className="flex min-w-0 flex-col text-left leading-tight group-data-[collapsible=icon]:hidden">
                                    <span className="truncate font-semibold">Desgarrados</span>
                                    <span className="truncate text-xs text-muted-foreground">{capabilities?.accessAdmin ? 'Painel editorial' : 'Minha conta'}</span>
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                {capabilities?.accessAdmin && <SidebarGroup>
                    <SidebarGroupLabel>Visão geral</SidebarGroupLabel>
                    <SidebarGroupContent><SidebarMenu>{menuItem({ href: '/admin', label: 'Painel da redação', icon: Home })}</SidebarMenu></SidebarGroupContent>
                </SidebarGroup>}
                {groups.map(group => <SidebarGroup key={group.label}>
                    <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                    <SidebarGroupContent><SidebarMenu>{group.items.map(menuItem)}</SidebarMenu></SidebarGroupContent>
                </SidebarGroup>)}
            </SidebarContent>
        </Sidebar>
        <SidebarInset className="admin-main">
            <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b border-border bg-background/95 px-3 backdrop-blur sm:gap-3 sm:px-6">
                <SidebarTrigger className="-ml-1 shrink-0" aria-label="Alternar menu lateral" />
                <div className="min-w-0 flex-1 overflow-hidden">
                    <Breadcrumbs breadcrumbs={[{ title: 'Desgarrados', href: capabilities?.accessAdmin ? '/admin' : '/' }, ...(title !== 'Visão geral' ? [{ title, href: currentPath }] : [])]} />
                </div>
                <Link href="/" className="inline-flex min-h-9 shrink-0 items-center justify-center gap-1 rounded-md px-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground sm:px-3" aria-label="Ver portal" title="Ver portal">
                    <ExternalLink className="size-4" aria-hidden="true" />
                    <span className="hidden sm:inline">Ver portal</span>
                </Link>
                {auth.user && <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-10 shrink-0 gap-2 px-2" aria-label={`Abrir menu da conta de ${auth.user.name}`}>
                            <Avatar className="size-8">
                                <AvatarFallback className="bg-primary text-xs font-semibold text-primary-foreground">{initials(auth.user.name)}</AvatarFallback>
                            </Avatar>
                            <span className="hidden max-w-36 flex-col items-start text-left leading-tight sm:flex">
                                <span className="truncate text-sm font-semibold">{auth.user.name}</span>
                                <span className="text-xs text-muted-foreground">{roleLabel}</span>
                            </span>
                            <ChevronDown className="size-4 text-muted-foreground" aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" side="bottom" sideOffset={8} collisionPadding={12} className="w-64 max-w-[calc(100vw-1.5rem)]">
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-3 px-2 py-2">
                                <Avatar>
                                    <AvatarFallback className="bg-primary font-semibold text-primary-foreground">{initials(auth.user.name)}</AvatarFallback>
                                </Avatar>
                                <span className="flex min-w-0 flex-col">
                                    <span className="truncate text-sm font-semibold">{auth.user.name}</span>
                                    <span className="text-xs text-muted-foreground">{roleLabel}</span>
                                </span>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild><Link href="/settings/profile"><Users />Meu perfil</Link></DropdownMenuItem>
                            <DropdownMenuItem asChild><Link href="/settings/security"><Settings />Segurança</Link></DropdownMenuItem>
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger><Palette />Aparência</DropdownMenuSubTrigger>
                                <DropdownMenuSubContent className="min-w-36">
                                    <DropdownMenuRadioGroup value={appearance} onValueChange={value => updateAppearance(value as Appearance)}>
                                        <DropdownMenuRadioItem value="light"><Sun />Claro</DropdownMenuRadioItem>
                                        <DropdownMenuRadioItem value="dark"><Moon />Escuro</DropdownMenuRadioItem>
                                        <DropdownMenuRadioItem value="system"><Settings />Sistema</DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                            <DropdownMenuItem asChild><Link href="/"><FolderOpen />Ver portal</Link></DropdownMenuItem>
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild variant="destructive">
                            <Link href="/logout" method="post" as="button" data-test="logout-button"><LogOut />Sair</Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>}
            </header>
            <main className="admin-content min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">{children}</main>
        </SidebarInset>
        <Toaster />
    </SidebarProvider>;
}
