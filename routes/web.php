<?php

use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\MyRegionController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProductionGuideController;
use App\Http\Controllers\RegionalEventController;
use App\Http\Controllers\RegionalHubController;
use App\Http\Controllers\RuralCalendarController;
use App\Http\Controllers\SearchSuggestionController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\StoryContributionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [PortalController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/nossa-terra', RegionalHubController::class)->name('nossa-terra');
Route::get('/busca/sugestoes', SearchSuggestionController::class)->middleware('throttle:60,1')->name('search.suggestions');
Route::get('/minha-regiao', MyRegionController::class)->name('my-region');
Route::get('/calendario-rural', [RuralCalendarController::class, 'index'])->name('rural-calendar.index');
Route::post('/calendario-rural/localizacao', [RuralCalendarController::class, 'reverseGeocode'])->middleware('throttle:5,1')->name('rural-calendar.reverse-geocode');
Route::get('/agenda-regional', [RegionalEventController::class, 'index'])->name('regional-events.index');
Route::get('/agenda-regional/{event:slug}', [RegionalEventController::class, 'show'])->name('regional-events.show');
Route::get('/nossa-terra/producao', [ProductionGuideController::class, 'index'])->name('production-guides.index');
Route::get('/nossa-terra/producao/{guide:slug}', [ProductionGuideController::class, 'show'])->name('production-guides.show');
Route::get('/cadernos/{slug}', [PortalController::class, 'notebook'])->name('notebook.show');
Route::get('/publicacao/{slug}', [PortalController::class, 'show'])->name('publication.show');
Route::get('/contribua', [PortalController::class, 'contribute'])->name('contribute.create');
Route::get('/contribua/obrigado', fn () => Inertia::render('portal/thanks'))->name('contribute.thanks');
Route::post('/contribua', [StoryContributionController::class, 'store'])->middleware('throttle:3,60')->name('contribute.store');
Route::get('/colecoes/{slug}', [SitePageController::class, 'collection'])->name('collection.show');
Route::get('/ditados', [SitePageController::class, 'sayings'])->name('sayings.index');
Route::get('/sobre', [SitePageController::class, 'show'])->defaults('slug', 'sobre')->name('pages.about');
Route::get('/proposta-cultural', [SitePageController::class, 'show'])->defaults('slug', 'proposta-cultural')->name('pages.proposal');
Route::get('/contato', [SitePageController::class, 'show'])->defaults('slug', 'contato')->name('pages.contact');
Route::post('/contato', [ContactMessageController::class, 'store'])->middleware('throttle:5,10')->name('pages.contact.store');
// The full policy is the official address; the old short page stays reachable for existing links.
Route::get('/privacidade', fn () => redirect('/politica-de-privacidade', 301))->name('pages.privacy');
Route::get('/termos-de-contribuicao', [SitePageController::class, 'show'])->defaults('slug', 'termos-de-contribuicao')->name('pages.terms');
Route::get('/termos-de-uso', [LegalDocumentController::class, 'terms'])->name('legal.terms');
Route::get('/politica-de-privacidade', [LegalDocumentController::class, 'privacy'])->name('legal.privacy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn (Request $request) => $request->user()?->canAccessAdmin()
        ? to_route('admin.dashboard')
        : to_route('home'))->name('dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

Route::fallback(fn (Request $request) => $request->is('admin/*') || $request->is('admin')
    ? app(SitePageController::class)->adminNotFound()
    : app(SitePageController::class)->notFound());
