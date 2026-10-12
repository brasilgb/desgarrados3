<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = Category::withCount(['categorizedPublications as publications_count'])->orderBy('name');
        if (filled($filters['q'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['q'].'%');
        }

        return Inertia::render('admin/categories/index', ['categories' => $query->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function store(Request $r): RedirectResponse
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:250']);
        $base = Str::slug($d['name']);
        $d['slug'] = $base;
        $suffix = 2;
        while (Category::where('slug', $d['slug'])->exists()) {
            $d['slug'] = $base.'-'.$suffix++;
        }
        Category::create($d);

        return back()->with('success', 'Assunto criado.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', 'Assunto removido.');
    }
}
