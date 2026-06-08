<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class EmailContentService
{
    public function cleanText(?string $text): ?string
    {
        if (! $text) {
            return $text;
        }

        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $patterns = [
            '/\n\s*Da:\s.+\n\s*Data:\s.+\n\s*A:\s.+\n\s*Oggetto:\s.+/isu',
            '/\n\s*From:\s.+\n\s*(?:Sent|Date):\s.+\n\s*To:\s.+\n\s*Subject:\s.+/isu',
            '/\n\s*Il giorno .+ ha scritto:\s*/isu',
            '/\n\s*On .+ wrote:\s*/isu',
            '/\n\s*-{2,}\s*(?:Messaggio originale|Original Message)\s*-{2,}.*/isu',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        $lines = collect(explode("\n", $text))
            ->map(fn ($line) => rtrim($line))
            ->values();

        while ($lines->isNotEmpty() && trim((string) $lines->last()) === '') {
            $lines->pop();
        }

        return trim($lines->implode("\n"));
    }

    public function cleanHtml(?string $html): ?string
    {
        if (! $html) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        $selectors = [
            '//*[@id="divRplyFwdMsg"]',
            '//*[@id="appendonsend"]',
            '//*[@id="replyForwardMsg"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " gmail_quote ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " yahoo_quoted ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " protonmail_quote ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " moz-cite-prefix ")]',
            '//blockquote[@type="cite"]',
        ];

        $removedNodes = 0;

        foreach ($selectors as $selector) {
            foreach (iterator_to_array($xpath->query($selector) ?: []) as $node) {
                $this->removeNodeAndFollowingSiblings($node);
                $removedNodes++;
            }
        }

        if ($removedNodes === 0) {
            $originalText = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $cleanedText = $this->cleanText($originalText);

            if ($cleanedText !== $originalText) {
                return nl2br(htmlspecialchars((string) $cleanedText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            }
        }

        $body = $xpath->query('//body')->item(0);
        $cleaned = $body instanceof DOMElement
            ? $this->innerHtml($body)
            : $document->saveHTML();

        return trim((string) $cleaned) ?: null;
    }

    private function removeNodeAndFollowingSiblings(DOMNode $node): void
    {
        while ($node->nextSibling) {
            $node->parentNode?->removeChild($node->nextSibling);
        }

        $node->parentNode?->removeChild($node);
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument?->saveHTML($child);
        }

        return $html;
    }
}
