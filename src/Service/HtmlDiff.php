<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Service;

/** Annotates native rendered HTML text without flattening tables, links or media. */
final class HtmlDiff
{
    public function __construct(private readonly TokenDiff $diff)
    {
    }

    public function compare(string $left, string $right): array
    {
        [$old, $oldNodes, $oldTokens] = $this->document($left);
        [$new, $newNodes, $newTokens] = $this->document($right);
        [$unified, $unifiedNodes] = $this->document($right);
        $changes = $this->diff->compare($oldTokens, $newTokens);
        $this->annotate($old, $oldNodes, $changes['left']);
        $this->annotate($new, $newNodes, $changes['right']);
        $this->annotate($unified, $unifiedNodes, $changes['right'], $changes['insertions']);

        return ['left' => $this->html($old), 'right' => $this->html($new), 'unified' => $this->html($unified), 'coarse' => $changes['coarse']];
    }

    private function document(string $html): array
    {
        if (strlen($html) > 262144) {
            throw new \LengthException('The rendered field is too large for an interactive preview.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body><main id="vc-root">'.$html.'</main></body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->getElementById('vc-root');
        if (!$root instanceof \DOMElement) {
            throw new \UnexpectedValueException('The native field preview could not be processed.');
        }
        // Scripts are unnecessary in the sandboxed read-only preview.
        $xpath = new \DOMXPath($document);
        foreach (iterator_to_array($xpath->query('.//script', $root)) as $script) {
            $script->parentNode?->removeChild($script);
        }
        $nodes = [];
        $tokens = [];
        foreach ($xpath->query('.//text()[not(ancestor::style)]', $root) as $node) {
            $nodes[] = $node;
            array_push($tokens, ...$this->diff->tokens($node->nodeValue));
        }

        return [$document, $nodes, $tokens];
    }

    private function annotate(\DOMDocument $document, array $nodes, array $flags, array $insertions = []): void
    {
        $index = 0;
        foreach ($nodes as $node) {
            $fragment = $document->createDocumentFragment();
            foreach ($this->diff->tokens($node->nodeValue) as $token) {
                if (isset($insertions[$index])) {
                    $this->append($document, $fragment, $insertions[$index], 2);
                    unset($insertions[$index]);
                }
                $this->append($document, $fragment, $token, $flags[$index] ?? 0);
                ++$index;
            }
            $node->parentNode?->replaceChild($fragment, $node);
        }
        foreach ($insertions as $text) {
            $this->append($document, $document->getElementById('vc-root'), $text, 2);
        }
    }

    private function append(\DOMDocument $document, \DOMNode $parent, string $text, int $type): void
    {
        if ($type === 0) {
            $parent->appendChild($document->createTextNode($text));

            return;
        }
        $last = $parent->lastChild;
        $tag = $type === 1 ? 'ins' : 'del';
        if (!$last instanceof \DOMElement || $last->tagName !== $tag || $last->getAttribute('class') !== ($type === 1 ? 'vc-added' : 'vc-removed')) {
            $last = $document->createElement($tag);
            $last->setAttribute('class', $type === 1 ? 'vc-added' : 'vc-removed');
            $parent->appendChild($last);
        }
        $last->appendChild($document->createTextNode($text));
    }

    private function html(\DOMDocument $document): string
    {
        $html = '';
        foreach ($document->getElementById('vc-root')->childNodes as $node) {
            $html .= $document->saveHTML($node);
        }

        return $html;
    }
}
