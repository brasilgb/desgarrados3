<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalHeroAssetsTest extends TestCase
{
    public function test_all_public_hero_files_exist_and_are_readable(): void
    {
        foreach ([
            'images/brand/hero-oficial.png',
            'images/brand/hero-oficial.webp',
            'images/brand/hero-cultura.webp',
            'images/brand/hero-nossa-terra.webp',
            'images/brand/hero-nossa-terra.png',
            'images/brand/hero-sobre.webp',
        ] as $asset) {
            $path = public_path($asset);

            $this->assertFileExists($path, "Missing public hero: {$asset}");
            $this->assertTrue(is_readable($path), "Unreadable public hero: {$asset}");
            $this->assertNotFalse(getimagesize($path), "Invalid image file: {$asset}");
        }
    }

    public function test_home_and_internal_pages_use_existing_hero_sources(): void
    {
        $home = file_get_contents(resource_path('js/pages/portal/home.tsx'));
        foreach (['/images/brand/hero-oficial.webp', '/images/brand/hero-oficial.png'] as $url) {
            $this->assertStringContainsString($url, $home, "home.tsx does not reference {$url}");
        }

        $component = file_get_contents(resource_path('js/components/portal-page-hero.tsx'));
        $sources = [
            'nossa-terra' => ['/images/brand/hero-nossa-terra.webp', '/images/brand/hero-nossa-terra.png'],
            'cultura' => ['/images/brand/hero-cultura.webp'],
            'sobre' => ['/images/brand/hero-sobre.webp'],
        ];
        foreach ($sources as $urls) {
            foreach ($urls as $url) {
                $this->assertStringContainsString($url, $component, "portal-page-hero.tsx does not reference {$url}");
                $this->assertFileExists(public_path(ltrim($url, '/')), "Missing hero asset: {$url}");
            }
        }

        // Every Nossa Terra page, not only the hub, must show the regional photograph.
        $pages = [
            'nossa-terra.tsx' => 'nossa-terra',
            'my-region.tsx' => 'nossa-terra',
            'rural-calendar.tsx' => 'nossa-terra',
            'regional-events.tsx' => 'nossa-terra',
            'production-guides.tsx' => 'nossa-terra',
            'notebook.tsx' => 'cultura',
            'collection.tsx' => 'cultura',
            'sayings.tsx' => 'cultura',
            'institutional.tsx' => 'sobre',
            'legal-document.tsx' => 'sobre',
            'contribute.tsx' => 'sobre',
        ];
        foreach ($pages as $page => $image) {
            $source = file_get_contents(resource_path("js/pages/portal/{$page}"));
            $this->assertMatchesRegularExpression('/<PortalPageHero\s+image="'.preg_quote($image, '/').'"/', $source, "{$page} does not use the {$image} hero");
        }

        $css = file_get_contents(resource_path('css/app.css'));
        $image = file_get_contents(resource_path('js/components/portal-hero-image.tsx'));
        $this->assertStringContainsString('.portal-hero-image {', $css);
        $this->assertStringContainsString('object-fit: cover', $css);
        $this->assertStringContainsString('min-height: 22rem', $css);
        $this->assertStringContainsString('min-height: 26rem', $css);
        $this->assertStringContainsString('src={webpSrc}', $image);
        $this->assertStringContainsString('onError={event =>', $image);
        $this->assertStringContainsString('whitespace-nowrap', file_get_contents(resource_path('js/pages/portal/notebook.tsx')));
    }

    public function test_public_logo_keeps_custom_preference_and_has_a_valid_default_fallback(): void
    {
        $source = file_get_contents(resource_path('js/layouts/public-layout.tsx'));

        $this->assertStringContainsString('settings.logo_path ?', $source);
        $this->assertStringContainsString('`/storage/${settings.logo_path}`', $source);
        $this->assertStringContainsString("event.currentTarget.src = '/images/brand/logo-lockup.png'", $source);
        $this->assertFileExists(public_path('images/brand/logo-lockup.png'));
    }
}
