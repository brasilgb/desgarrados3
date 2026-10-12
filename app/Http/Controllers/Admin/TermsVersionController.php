<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StorySubmission;
use App\Models\TermsVersion;
use Inertia\Inertia;
use Inertia\Response;

class TermsVersionController extends Controller
{
    /** Read-only view of the exact terms text a contributor accepted. */
    public function show(TermsVersion $termsVersion): Response
    {
        return Inertia::render('admin/terms-versions/show', [
            'version' => $termsVersion->only(['id', 'label', 'content_hash', 'source', 'title', 'body', 'created_at']),
            'acceptances' => StorySubmission::where('terms_version_id', $termsVersion->id)->count(),
        ]);
    }
}
