<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/collections/index', ['collections' => Collection::withCount('publications')->orderBy('name')->paginate(12)->withQueryString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'], 'is_featured' => ['nullable', 'boolean']]);
        $base = Str::slug($data['name']) ?: 'colecao';
        $slug = $base;
        for ($suffix = 2; Collection::where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }
        Collection::create([...$data, 'slug' => $slug, 'is_featured' => $data['is_featured'] ?? false]);

        return back()->with('success', 'Coleção criada. Associe publicações no editor.');
    }

    public function update(Request $request, Collection $collection): RedirectResponse
    {
        $data = $request->validate(['is_featured' => ['required', 'boolean']]);
        $collection->update($data);

        return back()->with('success', $collection->is_featured ? 'Coleção escolhida para a página inicial.' : 'Coleção retirada do destaque inicial.');
    }

    public function destroy(Collection $collection): RedirectResponse
    {
        $collection->delete();

        return back()->with('success', 'Coleção removida. As publicações foram preservadas.');
    }
}
