<?php

return [
    // Keep identity and contact details unpublished until the controller confirms them.
    'controller' => [
        'type' => env('LEGAL_CONTROLLER_TYPE', 'individual'),
        'name' => env('LEGAL_CONTROLLER_NAME', ''),
        'document' => env('LEGAL_CONTROLLER_DOCUMENT', ''),
        'address' => env('LEGAL_CONTROLLER_ADDRESS', ''),
        'contact' => env('LEGAL_CONTROLLER_CONTACT', ''),
        'identity_verified' => env('LEGAL_CONTROLLER_IDENTITY_VERIFIED', false),
        'public_identity_authorized' => env('LEGAL_CONTROLLER_PUBLIC_IDENTITY_AUTHORIZED', false),
        'personal_details_public' => env('LEGAL_CONTROLLER_PERSONAL_DETAILS_PUBLIC', false),
    ],
    'reviewed_for_production' => env('LEGAL_DOCUMENTS_REVIEWED_FOR_PRODUCTION', false),
];
