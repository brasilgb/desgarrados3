<?php

namespace App\Support;

use App\Models\Media;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class HtmlContentSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'img', 'figure', 'figcaption'];

    public function sanitize(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="content-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('content-root');
        if (! $root) {
            return '';
        }

        $this->sanitizeChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                continue;
            }

            if (! $node instanceof DOMElement) {
                $parent->removeChild($node);

                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form'], true)) {
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeChildren($node);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $attributes = [];
            if ($tag === 'a' && $node->hasAttribute('href')) {
                $href = preg_replace('/[\x00-\x20]+/', '', trim($node->getAttribute('href'))) ?? '';
                $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
                if ($scheme === '' || in_array($scheme, ['http', 'https', 'mailto'], true)) {
                    $attributes['href'] = $href;
                }
            }
            if ($tag === 'img') {
                $src = $node->getAttribute('src');
                $path = str_starts_with($src, '/storage/') ? substr($src, strlen('/storage/')) : '';
                if ($path === '' || ! Media::query()->where('path', $path)->exists()) {
                    $parent->removeChild($node);

                    continue;
                }
                $attributes['src'] = '/storage/'.$path;
                $attributes['alt'] = $node->getAttribute('alt');
                $attributes['loading'] = 'lazy';
            }
            if ($node->hasAttribute('title') && $tag !== 'img') {
                $attributes['title'] = $node->getAttribute('title');
            }

            while ($node->attributes->length > 0) {
                $node->removeAttributeNode($node->attributes->item(0));
            }
            foreach ($attributes as $name => $value) {
                $node->setAttribute($name, $value);
            }
        }
    }
}
