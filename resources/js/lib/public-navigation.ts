import { BookOpen, BookText, CalendarDays, CalendarRange, Feather, Landmark, Laugh, MapPinned, MessageCircle, Music2, Quote, Sprout, Wheat, type LucideIcon } from 'lucide-react';

type PublicNavigationLink = { href: string; label: string; description?: string; icon: LucideIcon };

export const cultureLinks: PublicNavigationLink[] = [
    { href: '/cadernos/historias', label: 'Histórias', icon: BookOpen },
    { href: '/cadernos/causos', label: 'Causos', icon: MessageCircle },
    { href: '/cadernos/anedotas', label: 'Anedotas', icon: Laugh },
    { href: '/cadernos/poesia', label: 'Poesia', icon: Feather },
    { href: '/cadernos/literatura', label: 'Literatura', icon: BookText },
    { href: '/cadernos/musica', label: 'Música', icon: Music2 },
    { href: '/cadernos/tradicoes', label: 'Tradições', icon: Landmark },
    { href: '/ditados', label: 'Ditados e expressões', icon: Quote },
];

export const landLinks: PublicNavigationLink[] = [
    { href: '/minha-regiao', label: 'Minha Região', description: 'Histórias e informações locais', icon: MapPinned },
    { href: '/nossa-terra', label: 'Central Campeira', description: 'Ferramentas e calculadoras', icon: Wheat },
    { href: '/calendario-rural', label: 'Calendário rural e ZARC', description: 'Atividades e zoneamento', icon: CalendarDays },
    { href: '/agenda-regional', label: 'Agenda regional', description: 'Eventos e feriados', icon: CalendarRange },
    { href: '/nossa-terra/producao', label: 'Produção rural', description: 'Guias e referências', icon: Sprout },
];
