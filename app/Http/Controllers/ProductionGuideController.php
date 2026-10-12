<?php

namespace App\Http\Controllers;

use App\Models\ProductionGuide;
use App\Models\TerritorialUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductionGuideController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['category' => ['nullable', 'string', 'max:80'], 'state' => ['nullable', 'string', 'size:2']]);
        $query = ProductionGuide::query()->where('status', 'published')
            ->when($filters['category'] ?? null, fn ($builder, $category) => $builder->where('category', $category))
            ->when($filters['state'] ?? null, fn ($builder, $state) => $builder->where(fn ($builder) => $builder->whereNull('state_code')->orWhere('state_code', $state)));
        $states = TerritorialUnit::query()->where('unit_type', 'state')->orderBy('name')->pluck('state_code');
        if ($states->isEmpty()) {
            $states = ProductionGuide::query()->where('status', 'published')->whereNotNull('state_code')->distinct()->orderBy('state_code')->pluck('state_code');
        }

        return Inertia::render('portal/production-guides', [
            'guides' => $query->orderBy('category')->orderBy('title')->paginate(18)->withQueryString(),
            'filters' => $filters,
            'categories' => ProductionGuide::query()->where('status', 'published')->distinct()->orderBy('category')->pluck('category'),
            'states' => $states,
        ]);
    }

    public function show(ProductionGuide $guide): Response
    {
        abort_unless($guide->status === 'published', 404);

        return Inertia::render('portal/production-guide', ['guide' => $guide]);
    }
}
