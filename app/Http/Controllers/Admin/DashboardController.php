<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Publication;
use App\Models\StorySubmission;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();

        return Inertia::render('admin/dashboard', [
            'currentUser' => ['name' => $user->name, 'role' => $user->role->value],
            'capabilities' => ['accessAdmin' => $user->canAccessAdmin(), 'manageUsers' => $user->canManageUsers()],
            'stats' => ['publications' => Publication::count(), 'published' => Publication::where('status', 'published')->count(), 'drafts' => Publication::where('status', 'draft')->count(), 'scheduled' => Publication::where('status', 'scheduled')->count(), 'categories' => Category::count(), 'media' => Media::count(), 'submissions' => StorySubmission::where('status', 'pending')->count(), 'submissionTotals' => StorySubmission::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')],
        ]);
    }
}
