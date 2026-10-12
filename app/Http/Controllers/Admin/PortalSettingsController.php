<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\PortalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PortalSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = PortalSetting::query()->pluck('value', 'key')->all();
        $settings += [
            'legal_controller_type' => config('legal.controller.type', 'individual'),
            'legal_controller_name' => config('legal.controller.name', ''),
            'legal_controller_document' => config('legal.controller.document', ''),
            'legal_controller_address' => config('legal.controller.address', ''),
            'legal_controller_identity_verified' => config('legal.controller.identity_verified') ? '1' : '0',
            'legal_controller_public_identity_authorized' => config('legal.controller.public_identity_authorized') ? '1' : '0',
            'legal_controller_personal_details_public' => config('legal.controller.personal_details_public') ? '1' : '0',
        ];
        $controllerName = $settings['legal_controller_name'] ?? '';
        $controllerContact = filled($settings['contact_email'] ?? null)
            ? $settings['contact_email']
            : config('legal.controller.contact', '');
        $identityConfirmed = ($settings['legal_controller_type'] ?? 'individual') === 'individual'
            && ($settings['legal_controller_identity_verified'] ?? '0') === '1'
            && filled($controllerName)
            && filled($controllerContact);

        return Inertia::render('admin/settings/portal', [
            'settings' => $settings,
            'legalReview' => [
                'identityConfirmed' => $identityConfirmed,
                'publicIdentityAuthorized' => ($settings['legal_controller_public_identity_authorized'] ?? '0') === '1',
                'personalDetailsAuthorized' => ($settings['legal_controller_personal_details_public'] ?? '0') === '1',
                'productionReviewed' => (bool) config('legal.reviewed_for_production'),
            ],
            'media' => Media::latest()->get(['path', 'original_name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'portal_name' => ['required', 'string', 'max:120'],
            'portal_description' => ['required', 'string', 'max:500'],
            'institutional_intro' => ['required', 'string', 'max:2000'],
            'logo_path' => ['nullable', 'exists:media,path'],
            'share_image_path' => ['nullable', 'exists:media,path'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'legal_controller_type' => ['required', 'in:individual'],
            'legal_controller_name' => ['nullable', 'string', 'max:180'],
            'legal_controller_document' => ['nullable', 'string', 'max:30'],
            'legal_controller_address' => ['nullable', 'string', 'max:255'],
            'legal_controller_identity_verified' => ['required', 'boolean'],
            'legal_controller_public_identity_authorized' => ['required', 'boolean'],
            'legal_controller_personal_details_public' => ['required', 'boolean'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'show_sayings' => ['required', 'boolean'],
            'show_collections' => ['required', 'boolean'],
            'show_categories' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data as $key => $value) {
                PortalSetting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : ($value ?? '')]);
            }
        });

        return back()->with('success', 'Configurações do portal atualizadas.');
    }
}
