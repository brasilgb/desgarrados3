<?php

namespace App\Http\Controllers;

use App\Models\StorySubmission;
use App\Support\InstitutionalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoryContributionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return to_route('contribute.thanks');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'min:8', 'max:180'],
            'story' => ['required', 'string', 'min:80', 'max:12000'],
            'contributor_name' => ['nullable', 'string', 'max:120'],
            'origin_city' => ['nullable', 'string', 'max:120'],
            'origin_state' => ['nullable', 'string', 'size:2'],
            'contact_email' => ['nullable', 'email', 'max:255', 'required_with:contact_consent'],
            'contact_consent' => [Rule::excludeIf(blank($request->input('contact_email'))), 'accepted'],
            'rights_confirmed' => ['accepted'],
            'publication_consent' => ['accepted'],
            'terms_accepted' => ['accepted'],
            'display_name_consent' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=6000,max_height=6000', 'max:5120'],
            'photo_credit' => ['nullable', 'string', 'max:180', 'required_with:photo'],
            'photo_publication_consent' => [Rule::excludeIf(! $request->hasFile('photo')), 'accepted'],
            'website' => ['nullable', 'prohibited'],
        ]);
        // Record the exact text shown now; later edits create new versions and never touch this one.
        $terms = InstitutionalPages::currentTerms();
        if (! $terms) {
            throw ValidationException::withMessages(['terms_accepted' => 'Os Termos de envio estão em revisão. Tente enviar novamente mais tarde.']);
        }
        unset($data['website']);
        if (blank($data['contact_email'] ?? null)) {
            $data['contact_consent'] = false;
        }
        $data['display_name_consent'] = (bool) ($data['display_name_consent'] ?? false);
        if ($request->hasFile('photo')) {
            $data['photo_original_name'] = mb_substr(basename($request->file('photo')->getClientOriginalName()), 0, 180);
            $data['photo_path'] = $request->file('photo')->store('story-submissions', 'local');
        }
        unset($data['photo']);
        $data['photo_publication_consent'] = (bool) ($data['photo_publication_consent'] ?? false);
        unset($data['terms_accepted']);
        $data['terms_accepted_at'] = now();
        $data['terms_version'] = $terms->label;
        $data['terms_version_id'] = $terms->id;
        $data['status'] = 'pending';

        StorySubmission::create($data);

        return to_route('contribute.thanks');
    }
}
