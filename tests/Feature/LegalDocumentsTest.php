<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Support\InstitutionalPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_are_public_and_pending_controller_details_are_not_invented(): void
    {
        config(['legal.controller.name' => '', 'legal.controller.contact' => '', 'legal.controller.identity_verified' => false, 'legal.controller.public_identity_authorized' => false]);
        config(['legal.reviewed_for_production' => false]);

        $this->get('/termos-de-uso')->assertOk()->assertInertia(fn ($page) => $page
            ->component('portal/legal-document')
            ->where('document', 'terms')
            ->where('controller', null)
            ->where('reviewedForProduction', false));

        $this->get('/politica-de-privacidade')->assertOk()->assertInertia(fn ($page) => $page
            ->component('portal/legal-document')
            ->where('document', 'privacy')
            ->where('controller', null)
            ->where('reviewedForProduction', false));
    }

    public function test_controller_details_are_sent_only_after_explicit_verification(): void
    {
        config(['legal.controller' => ['type' => 'individual', 'name' => 'Controlador de teste', 'document' => '', 'address' => '', 'contact' => 'privacidade@example.test', 'identity_verified' => true, 'public_identity_authorized' => true, 'personal_details_public' => false]]);
        config(['legal.reviewed_for_production' => true]);

        $this->get('/politica-de-privacidade')->assertOk()->assertInertia(fn ($page) => $page
            ->where('controller.name', 'Controlador de teste')
            ->where('controller.contact', 'privacidade@example.test')
            ->where('controller.document', '')
            ->where('controller.address', '')
            ->where('reviewedForProduction', true));
    }

    public function test_panel_identity_fields_are_used_only_after_explicit_confirmation(): void
    {
        config(['legal.controller' => ['type' => 'individual', 'name' => '', 'document' => '', 'address' => '', 'contact' => '', 'identity_verified' => false, 'public_identity_authorized' => false, 'personal_details_public' => false]]);
        config(['legal.reviewed_for_production' => false]);
        foreach ([
            'legal_controller_name' => 'Controlador cadastrado',
            'legal_controller_document' => 'documento cadastrado',
            'legal_controller_address' => 'endereço cadastrado',
            'legal_controller_type' => 'individual',
            'legal_controller_identity_verified' => '0',
            'legal_controller_public_identity_authorized' => '0',
            'legal_controller_personal_details_public' => '0',
            'contact_email' => 'privacidade@example.test',
        ] as $key => $value) {
            PortalSetting::create(['key' => $key, 'value' => $value]);
        }

        $this->get('/politica-de-privacidade')->assertOk()->assertInertia(fn ($page) => $page->where('controller', null)->where('reviewedForProduction', false));

        PortalSetting::where('key', 'legal_controller_identity_verified')->update(['value' => '1']);
        $this->get('/politica-de-privacidade')->assertOk()->assertInertia(fn ($page) => $page->where('controller', null)->where('reviewedForProduction', false));

        PortalSetting::where('key', 'legal_controller_public_identity_authorized')->update(['value' => '1']);
        $this->get('/politica-de-privacidade')->assertOk()->assertInertia(fn ($page) => $page
            ->where('controller.name', 'Controlador cadastrado')
            ->where('controller.contact', 'privacidade@example.test')
            ->where('controller.document', '')
            ->where('controller.address', ''));

        PortalSetting::where('key', 'legal_controller_personal_details_public')->update(['value' => '1']);
        $this->get('/politica-de-privacidade')->assertOk()->assertInertia(fn ($page) => $page
            ->where('controller.document', 'documento cadastrado')
            ->where('controller.address', 'endereço cadastrado'));
    }

    public function test_default_submission_terms_define_a_limited_license_and_separate_image_rights(): void
    {
        $body = InstitutionalPages::default(InstitutionalPages::TERMS_SLUG)['body'];

        $this->assertStringContainsString('licença não exclusiva', $body);
        $this->assertStringContainsString('não autoriza exploração comercial', $body);
        $this->assertStringContainsString('autorização de uso de imagem', $body);
        $this->assertStringContainsString('criança ou adolescente', $body);
        $this->assertStringContainsString('não recebe áudio ou vídeo', $body);
    }
}
