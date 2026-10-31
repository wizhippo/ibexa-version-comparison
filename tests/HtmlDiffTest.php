<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use PHPUnit\Framework\TestCase;
use Wizhippo\IbexaVersionComparison\Service\HtmlDiff;
use Wizhippo\IbexaVersionComparison\Service\PreviewDocument;
use Wizhippo\IbexaVersionComparison\Service\TokenDiff;

final class HtmlDiffTest extends TestCase
{
    public function testNativeStructureAndMediaAreRetainedWithInlineChanges(): void
    {
        $left = '<h2>Itinerary</h2><table><tbody><tr><td><p>Old port</p></td></tr></tbody></table><img src="/image.jpg" alt="Ship"><a href="/resource">Read more</a>';
        $right = str_replace('Old port', 'New port', $left);
        $result = (new HtmlDiff(new TokenDiff()))->compare($left, $right);
        foreach (['left', 'right', 'unified'] as $side) {
            self::assertStringContainsString('<h2>Itinerary</h2>', $result[$side]);
            self::assertStringContainsString('<table>', $result[$side]);
            self::assertStringContainsString('src="/image.jpg"', $result[$side]);
            self::assertStringContainsString('href="/resource"', $result[$side]);
        }
        self::assertStringContainsString('<del class="vc-removed">Old</del>', $result['left']);
        self::assertStringContainsString('<ins class="vc-added">New</ins>', $result['right']);
        self::assertStringContainsString('<del class="vc-removed">Old</del>', $result['unified']);
    }

    public function testFormattingOnlyChangesDoNotInventTextEdits(): void
    {
        $result = (new HtmlDiff(new TokenDiff()))->compare('<p>Hello <strong>world</strong></p>', '<h2>Hello world</h2>');
        self::assertStringNotContainsString('<ins', $result['right']);
        self::assertStringNotContainsString('<del', $result['left']);
        self::assertStringContainsString('<h2>', $result['unified']);
    }

    public function testRemovedTrailingContentIsVisibleInUnifiedViewAndScriptsAreRemoved(): void
    {
        $result = (new HtmlDiff(new TokenDiff()))->compare('<p>First.</p><p>Removed.</p>', '<p>First.</p><script>alert(1)</script>');
        self::assertStringContainsString('Removed', $result['unified']);
        self::assertStringNotContainsString('<script', $result['right']);
        self::assertStringNotContainsString('alert(1)', $result['right']);
    }

    public function testPreviewBaseUrlCannotInjectAttributes(): void
    {
        $html = (new PreviewDocument())->create('<p>Content</p>', 'https://localhost/" onload="alert(1)');
        self::assertStringNotContainsString(' onload="alert', $html);
        self::assertStringContainsString('&quot;', $html);
    }
}
