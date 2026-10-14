<?php

namespace App\Services\EditorialImport;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Reads a Wikimedia Commons file page and downloads it only when its licence allows reuse.
 * The licence comes from the file's own Commons metadata, never from the editorial dataset.
 */
class CommonsImageFetcher
{
    private const API = 'https://commons.wikimedia.org/w/api.php';

    private const USER_AGENT = 'DesgarradosAcervo/1.0 (https://desgarrados.com.br; acervo editorial)';

    public const PAUSE_MS = 1500;

    /** Free licences accepted for publication; NC and ND variants are refused by the pattern below. */
    private const ACCEPTED = '/^(public domain|pd([- ].*)?|cc0( 1\.0)?|cc[- ]by(-sa)?[- ]?[1-4]\.\d.*|cc[- ]by(-sa)?)$/i';

    /**
     * @return array{accepted: bool, reason: string|null, url: string|null, mime: string|null, provenance: array<string, string|null>}
     */
    public function inspect(string $title): array
    {
        $title = str_starts_with($title, 'File:') ? $title : 'File:'.$title;
        $response = $this->client(30)->get(self::API, [
            'action' => 'query', 'format' => 'json', 'formatversion' => 2, 'titles' => $title,
            'prop' => 'imageinfo', 'iiprop' => 'url|mime|extmetadata', 'iiurlwidth' => 1600,
        ]);
        $page = $response->json('query.pages.0');
        $info = $page['imageinfo'][0] ?? null;
        if (! $response->successful() || ! $info) {
            return ['accepted' => false, 'reason' => 'Arquivo não encontrado no Commons.', 'url' => null, 'mime' => null, 'provenance' => ['title' => $title]];
        }

        $meta = fn (string $key): ?string => isset($info['extmetadata'][$key]['value']) ? $this->plain((string) $info['extmetadata'][$key]['value']) : null;
        $license = $meta('LicenseShortName');
        $provenance = [
            'title' => $title,
            'description' => $meta('ImageDescription'),
            'author' => $this->author($meta('Artist')),
            'credit' => $meta('Credit'),
            'institution' => $meta('Credit'),
            'date' => $meta('DateTimeOriginal'),
            'license' => $license,
            'license_url' => $meta('LicenseUrl'),
            'source_name' => 'Wikimedia Commons',
            'source_url' => $info['descriptionurl'] ?? null,
            'accessed_at' => now()->toDateString(),
        ];

        $reason = null;
        if (! $license || ! preg_match(self::ACCEPTED, trim($license))) {
            $reason = 'Licença não aceita para reprodução: '.($license ?: 'não informada').'.';
        } elseif (filled($meta('Restrictions'))) {
            $reason = 'Arquivo com restrições adicionais de uso: '.$meta('Restrictions').'.';
        } elseif (! str_starts_with((string) ($info['mime'] ?? ''), 'image/') || in_array($info['mime'], ['image/svg+xml', 'image/tiff'], true)) {
            $reason = 'Formato de arquivo não suportado no portal.';
        }

        return [
            'accepted' => $reason === null,
            'reason' => $reason,
            'url' => $info['thumburl'] ?? $info['url'] ?? null,
            'mime' => $info['mime'] ?? null,
            'provenance' => $provenance,
        ];
    }

    public function download(string $url): ?string
    {
        // Wikimedia rate-limits bulk downloads; space them out to stay a polite client.
        usleep(self::PAUSE_MS * 1000);
        $response = $this->client(60)->get($url);

        return $response->successful() ? $response->body() : null;
    }

    /** Retries rate-limited and failed requests, honouring Retry-After, without throwing. */
    private function client(int $timeout): PendingRequest
    {
        return Http::withUserAgent(self::USER_AGENT)->timeout($timeout)->retry(
            5,
            fn (int $attempt, \Throwable $exception): int => max(
                $exception instanceof RequestException ? (int) $exception->response->header('Retry-After') * 1000 : 0,
                2000 * $attempt,
            ),
            fn (\Throwable $exception): bool => ! $exception instanceof RequestException || $exception->response->status() === 429 || $exception->response->serverError(),
            throw: false,
        );
    }

    /** Portuguese label for the credit line; the original licence name stays in the provenance record. */
    public static function licenseLabel(?string $license): ?string
    {
        return match (true) {
            $license === null => null,
            (bool) preg_match('/^(public domain|pd([- ].*)?)$/i', $license) => 'Domínio público',
            (bool) preg_match('/^cc0/i', $license) => 'CC0 (domínio público)',
            default => $license,
        };
    }

    /** Commons often repeats the creator template ("Unknown authorUnknown author"). */
    private function author(?string $artist): ?string
    {
        if ($artist === null) {
            return null;
        }
        $artist = preg_replace('/^(.+?)\s*\1$/u', '$1', $artist) ?? $artist;

        return preg_match('/^(unknown( author| photographer)?|anonymous|desconhecido|autor desconhecido)$/i', trim($artist)) ? 'Autor desconhecido' : $artist;
    }

    private function plain(string $html): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $text === '' ? null : mb_substr($text, 0, 500);
    }
}
