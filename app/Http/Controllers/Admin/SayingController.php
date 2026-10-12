<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Saying;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SayingController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/sayings/index', ['sayings' => Saying::latest()->paginate(12)->withQueryString()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        Saying::create($data);

        return back()->with('success', 'Ditado salvo.');
    }

    public function update(Request $request, Saying $saying): RedirectResponse
    {
        $saying->update($this->validated($request));

        return back()->with('success', 'Ditado atualizado.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'phrase' => ['required', 'string', 'max:500'],
            'meaning' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'string', 'max:500'],
            'source_note' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,published'],
        ]);
    }

    public function destroy(Saying $saying): RedirectResponse
    {
        $saying->delete();

        return back()->with('success', 'Ditado removido.');
    }
}
