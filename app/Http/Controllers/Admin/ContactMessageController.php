<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/contact-messages/index', [
            'messages' => ContactMessage::latest()->paginate(20, ['id', 'name', 'email', 'subject', 'message', 'forwarded_at', 'read_at', 'created_at']),
            'unread' => ContactMessage::whereNull('read_at')->count(),
        ]);
    }

    public function markRead(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->forceFill(['read_at' => $contactMessage->read_at ? null : now()])->save();

        return back();
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return back()->with('success', 'Mensagem excluída.');
    }
}
