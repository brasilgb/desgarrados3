<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\PortalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactMessageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: bots get the same answer as people, but nothing is stored.
        if ($request->filled('website')) {
            return to_route('pages.contact')->with('success', 'Mensagem recebida. Obrigado pelo contato.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'privacy_acknowledged' => ['accepted'],
            'website' => ['nullable', 'prohibited'],
        ], [], [
            'name' => 'nome', 'email' => 'e-mail', 'subject' => 'assunto', 'message' => 'mensagem', 'privacy_acknowledged' => 'ciência da Política de Privacidade',
        ]);

        $message = ContactMessage::create([
            'name' => strip_tags($data['name']),
            'email' => $data['email'],
            'subject' => strip_tags($data['subject']),
            'message' => $data['message'],
            'privacy_acknowledged_at' => now(),
        ]);

        // The message is already stored for the editorial panel; e-mail is an extra notice when configured.
        $recipient = PortalSetting::query()->where('key', 'contact_email')->value('value');
        if (filled($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($recipient)->send(new ContactMessageReceived($message));
                $message->forceFill(['forwarded_at' => now()])->save();
            } catch (Throwable $exception) {
                // Transport errors often echo addresses; log only identifiers, never personal data.
                Log::warning('Contact message stored but e-mail notification failed.', ['contact_message_id' => $message->id, 'exception' => $exception::class]);
            }
        }

        return to_route('pages.contact')->with('success', 'Mensagem recebida. A equipe responderá pelo e-mail informado, quando necessário.');
    }
}
