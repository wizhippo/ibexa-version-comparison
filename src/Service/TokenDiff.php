<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Service;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/** Bounded, Unicode-aware word comparison. No source text is interpreted as markup. */
final class TokenDiff
{
    public function tokens(string $text): array
    {
        if ($text === '') {
            return [];
        }
        $result = preg_match_all('/\s+|[\p{L}\p{N}_]+|[^\s\p{L}\p{N}_]/u', $text, $matches);

        return $result === false ? [$text] : $matches[0];
    }

    public function compare(array $left, array $right): array
    {
        if (implode('', $left) === implode('', $right)) {
            return ['left' => array_fill(0, count($left), 0), 'right' => array_fill(0, count($right), 0), 'insertions' => [], 'unified' => self::escape(implode('', $right)), 'coarse' => false];
        }
        $prefix = 0;
        $leftEnd = count($left);
        $rightEnd = count($right);
        while ($prefix < min($leftEnd, $rightEnd) && $left[$prefix] === $right[$prefix]) {
            ++$prefix;
        }
        while ($leftEnd > $prefix && $rightEnd > $prefix && $left[$leftEnd - 1] === $right[$rightEnd - 1]) {
            --$leftEnd;
            --$rightEnd;
        }
        $old = array_slice($left, $prefix, $leftEnd - $prefix);
        $new = array_slice($right, $prefix, $rightEnd - $prefix);
        $coarse = count($old) * count($new) > 1000000 || count($left) + count($right) > 30000;
        $changes = array_map(static fn (string $token): array => [$token, 0], array_slice($left, 0, $prefix));
        $middle = $coarse
            ? array_merge(array_map(static fn (string $token): array => [$token, 2], $old), array_map(static fn (string $token): array => [$token, 1], $new))
            : (new Differ(new UnifiedDiffOutputBuilder()))->diffToArray($old, $new);
        $changes = array_merge($changes, $middle, array_map(static fn (string $token): array => [$token, 0], array_slice($left, $leftEnd)));
        $result = ['left' => [], 'right' => [], 'insertions' => [], 'unified' => '', 'coarse' => $coarse];
        $rightIndex = 0;
        $runs = [];
        foreach ($changes as [$text, $type]) {
            // The library may emit a line-ending diagnostic, which is not source text.
            if (!in_array($type, [0, 1, 2], true)) {
                continue;
            }
            if ($type === 2) {
                $result['left'][] = 2;
                $result['insertions'][$rightIndex] = ($result['insertions'][$rightIndex] ?? '').$text;
            } else {
                $result['right'][] = $type;
                ++$rightIndex;
                if ($type === 0) {
                    $result['left'][] = 0;
                }
            }
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last][1] === $type) {
                $runs[$last][0] .= $text;
            } else {
                $runs[] = [$text, $type];
            }
        }
        foreach ($runs as [$text, $type]) {
            $result['unified'] .= self::mark($text, $type);
        }

        return $result;
    }

    public function text(string $left, string $right): array
    {
        $old = $this->tokens($left);
        $new = $this->tokens($right);
        $result = $this->compare($old, $new);
        $result['leftHtml'] = $this->highlight($old, $result['left']);
        $result['rightHtml'] = $this->highlight($new, $result['right']);

        return $result;
    }

    private function highlight(array $tokens, array $flags): string
    {
        $html = '';
        $text = '';
        $previous = 0;
        foreach ($tokens as $index => $token) {
            $type = $flags[$index];
            if ($type !== $previous) {
                $html .= self::mark($text, $previous);
                $text = '';
            }
            $text .= $token;
            $previous = $type;
        }

        return $html.self::mark($text, $previous);
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function mark(string $text, int $type): string
    {
        $escaped = self::escape($text);

        return match ($type) {
            1 => '<ins class="vc-added">'.$escaped.'</ins>',
            2 => '<del class="vc-removed">'.$escaped.'</del>',
            default => $escaped,
        };
    }
}
