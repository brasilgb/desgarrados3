<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicationSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SeriesController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/series/index', ['series' => PublicationSeries::withCount('publications')->orderBy('title')->paginate(12)->withQueryString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:500']]);
        $base = Str::slug($data['title']) ?: 'serie';
        $slug = $base;
        for ($suffix = 2; PublicationSeries::where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }
        PublicationSeries::create([...$data, 'slug' => $slug]);

        return back()->with('success', 'Série criada. Selecione-a nas publicações que farão parte dela.');
    }
}
