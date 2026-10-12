<?php

namespace App\Http\Controllers;

use App\Models\PortalSetting;
use Inertia\Inertia;
use Inertia\Response;

class LegalDocumentController extends Controller
{
    public function terms(): Response
    {
        return $this->render('terms');
    }

    public function privacy(): Response
    {
        return $this->render('privacy');
    }

    private function render(string $document): Response
    {
        $legal = config('legal');
        $settings = PortalSetting::query()->whereIn('key', [
            'legal_controller_type', 'legal_controller_name', 'legal_controller_document', 'legal_controller_address',
            'legal_controller_identity_verified', 'legal_controller_public_identity_authorized',
            'legal_controller_personal_details_public', 'contact_email',
        ])->pluck('value', 'key');
        $controller = [
            'type' => $settings->get('legal_controller_type', $legal['controller']['type']),
            'name' => $settings->get('legal_controller_name', $legal['controller']['name']),
            'document' => $settings->get('legal_controller_document', $legal['controller']['document']),
            'address' => $settings->get('legal_controller_address', $legal['controller']['address']),
            'contact' => filled($settings->get('contact_email')) ? $settings->get('contact_email') : $legal['controller']['contact'],
            'identity_verified' => $settings->has('legal_controller_identity_verified')
                ? $settings->get('legal_controller_identity_verified') === '1'
                : $legal['controller']['identity_verified'],
            'public_identity_authorized' => $settings->has('legal_controller_public_identity_authorized')
                ? $settings->get('legal_controller_public_identity_authorized') === '1'
                : $legal['controller']['public_identity_authorized'],
            'personal_details_public' => $settings->has('legal_controller_personal_details_public')
                ? $settings->get('legal_controller_personal_details_public') === '1'
                : $legal['controller']['personal_details_public'],
        ];
        $identityVerified = (bool) $controller['identity_verified']
            && $controller['type'] === 'individual'
            && filled($controller['name'])
            && filled($controller['contact']);
        $publicIdentityAuthorized = (bool) $controller['public_identity_authorized'];
        $personalDetailsPublic = (bool) $controller['personal_details_public'];
        $visibleController = $identityVerified && $publicIdentityAuthorized
            ? [
                'name' => $controller['name'],
                'document' => $personalDetailsPublic ? $controller['document'] : '',
                'address' => $personalDetailsPublic ? $controller['address'] : '',
                'contact' => $controller['contact'],
            ]
            : null;

        return Inertia::render('portal/legal-document', [
            'document' => $document,
            'controller' => $visibleController,
            'reviewedForProduction' => (bool) $legal['reviewed_for_production'],
        ]);
    }
}
