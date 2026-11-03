<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class DeckHtml
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'caption', 'code', 'del', 'div', 'em', 'figcaption', 'figure',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'mark', 'ol', 'p', 'pre', 's', 'small', 'span',
        'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    private const DROP_TAGS = ['audio', 'base', 'button', 'embed', 'form', 'iframe', 'input', 'link', 'meta', 'object', 'script', 'select', 'source', 'style', 'svg', 'textarea', 'video'];

    private const ALLOWED_STYLES = [
        'align-items', 'background', 'background-color', 'border', 'border-color', 'border-radius', 'border-style', 'border-width',
        'box-shadow', 'color', 'display', 'flex', 'font-family', 'font-size', 'font-style', 'font-weight', 'gap', 'grid-template-columns', 'height', 'justify-content', 'letter-spacing', 'opacity',
        'line-height', 'list-style-type', 'margin', 'margin-bottom', 'margin-left', 'margin-right', 'margin-top',
        'max-width', 'min-height', 'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top',
        'text-align', 'text-decoration', 'vertical-align', 'white-space', 'width',
    ];

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $previousErrorMode = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<!doctype html><html><head><meta charset="UTF-8"></head><body><div id="deck-html-root">'.$html.'</div></body></html>',
            LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);

        $root = (new DOMXPath($document))->query('//*[@id="deck-html-root"]')->item(0);
        if (! $root) {
            return '';
        }

        self::cleanChildren($root);
        $clean = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $clean .= $document->saveHTML($child);
        }

        return $clean;
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_TAGS, true)) {
                $parent->removeChild($child);

                continue;
            }

            self::cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                $allowed = in_array($name, ['alt', 'class', 'colspan', 'height', 'href', 'id', 'rel', 'rowspan', 'src', 'style', 'target', 'title', 'width'], true);

                if (! $allowed || str_starts_with($name, 'on')) {
                    $child->removeAttributeNode($attribute);

                    continue;
                }

                if ($name === 'style') {
                    $style = self::sanitizeStyle($value);
                    if ($style === '') {
                        $child->removeAttribute('style');
                    } else {
                        $child->setAttribute('style', $style);
                    }
                } elseif ($name === 'href' && ! self::safeUrl($value, true)) {
                    $child->removeAttribute('href');
                } elseif ($name === 'src' && ! self::safeUrl($value)) {
                    $child->removeAttribute('src');
                } elseif (in_array($name, ['colspan', 'height', 'rowspan', 'width'], true) && ! preg_match('/^\d{1,4}%?$/', $value)) {
                    $child->removeAttribute($name);
                } elseif (in_array($name, ['class', 'id'], true) && ! preg_match('/^[\pL\pN _-]{1,160}$/u', $value)) {
                    $child->removeAttribute($name);
                } elseif ($name === 'target' && ! in_array($value, ['_blank', '_self'], true)) {
                    $child->removeAttribute($name);
                }
            }

            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private static function sanitizeStyle(string $style): string
    {
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            if (! in_array($property, self::ALLOWED_STYLES, true) || $value === '' || preg_match('/url\s*\(|expression|javascript|@import|\\\\/i', $value) || ! preg_match('/^[\pL\pN#(),.%\s+\-]+$/u', $value)) {
                continue;
            }

            $safe[] = $property.': '.$value;
        }

        return implode('; ', $safe);
    }

    private static function safeUrl(string $url, bool $allowAnchor = false): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20]/', $url)) {
            return false;
        }
        if ($allowAnchor && str_starts_with($url, '#')) {
            return true;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, $allowAnchor ? ['http', 'https', 'mailto'] : ['http', 'https'], true);
    }
}
