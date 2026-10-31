<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use PHPUnit\Framework\TestCase;
use Wizhippo\IbexaVersionComparison\Service\TokenDiff;

final class TokenDiffTest extends TestCase
{
    public function testWordChangesAreDirectionalAndEscaped(): void
    {
        $result = (new TokenDiff())->text('Visit <old> Montréal.', 'Visit <new> Montréal.');
        self::assertStringContainsString('<del class="vc-removed">old</del>', $result['leftHtml']);
        self::assertStringContainsString('<ins class="vc-added">new</ins>', $result['rightHtml']);
        self::assertStringNotContainsString('<old>', $result['unified']);
        self::assertStringContainsString('Montréal', $result['unified']);
        self::assertStringContainsString('&lt;', $result['unified']);
    }

    /** @dataProvider values */
    public function testSplitDiffsAlwaysReconstructTheirOriginalSide(string $left, string $right): void
    {
        $result = (new TokenDiff())->text($left, $right);
        self::assertSame($left, html_entity_decode(strip_tags($result['leftHtml']), ENT_QUOTES, 'UTF-8'));
        self::assertSame($right, html_entity_decode(strip_tags($result['rightHtml']), ENT_QUOTES, 'UTF-8'));
        self::assertStringNotContainsString('#Warning', $result['unified']);
    }

    public static function values(): iterable
    {
        yield 'addition' => ['', 'New content'];
        yield 'deletion' => ['Deleted content', ''];
        yield 'unchanged' => ['Hello world', 'Hello world'];
        yield 'line endings' => ["one\r\ntwo", "one\ntwo"];
        yield 'unicode and script text' => ['café ✈️ <script>alert(1)</script>', 'café 🛳️ & new'];
    }

    public function testLargeChangesUseBoundedComparisonAndRetainSharedEnds(): void
    {
        $result = (new TokenDiff())->text('Start '.str_repeat('old ', 1100).'Finish', 'Start '.str_repeat('new ', 1100).'Finish');
        self::assertTrue($result['coarse']);
        self::assertStringStartsWith('Start ', $result['unified']);
        self::assertStringEndsWith('Finish', $result['unified']);
    }
}
