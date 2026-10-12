import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import PortalHeroImage from '@/components/portal-hero-image';
import { cn } from '@/lib/utils';

const heroImages = {
    'nossa-terra': { webpSrc: '/images/brand/hero-nossa-terra.webp?v=3', fallbackSrc: '/images/brand/hero-nossa-terra.png?v=3' },
    cultura: { webpSrc: '/images/brand/hero-cultura.webp' },
    sobre: { webpSrc: '/images/brand/hero-sobre.webp' },
} as const;

const widths = { wide: 'max-w-7xl', medium: 'max-w-5xl', narrow: 'max-w-4xl', text: 'max-w-3xl' } as const;

type Props = {
    image: keyof typeof heroImages;
    eyebrow: ReactNode;
    title: ReactNode;
    intro?: ReactNode;
    icon?: LucideIcon;
    width?: keyof typeof widths;
    aside?: ReactNode;
    titleClassName?: string;
};

export default function PortalPageHero({ image, eyebrow, title, intro, icon: Icon, width = 'wide', aside, titleClassName }: Props) {
    return (
        <section className="portal-section-hero px-5 text-[#f5f0e6]">
            <PortalHeroImage {...heroImages[image]} />
            <div className={cn('portal-section-hero__content mx-auto grid w-full gap-10', widths[width], aside && 'md:grid-cols-[1.3fr_.7fr] md:items-end')}>
                <div>
                    <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-[.22em] text-[#d4a476]">
                        {Icon && <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />}
                        {eyebrow}
                    </p>
                    <h1 className={cn('mt-3 max-w-4xl font-serif text-4xl font-bold leading-tight text-balance md:text-5xl', titleClassName)}>{title}</h1>
                    {intro && <p className="mt-4 max-w-3xl text-base leading-7 text-[#dbe0d8] sm:text-lg sm:leading-8">{intro}</p>}
                </div>
                {aside}
            </div>
        </section>
    );
}
