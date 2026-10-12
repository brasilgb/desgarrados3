{{-- Plain-text mail: values are printed raw so characters like & are not turned into HTML entities. --}}
Nova mensagem enviada pela página de Contato do Desgarrados.

Nome: {!! $contactMessage->name !!}
E-mail: {!! $contactMessage->email !!}
Assunto: {!! $contactMessage->subject !!}
Recebida em: {{ $contactMessage->created_at?->timezone(config('app.display_timezone', 'America/Sao_Paulo'))->format('d/m/Y H:i') }}

{!! $contactMessage->message !!}

Responda diretamente a este e-mail para falar com a pessoa. A mensagem também fica registrada no painel, em Mensagens de contato.
