<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use Ibexa\Core\Repository\Values\Content\VersionInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Wizhippo\IbexaVersionComparison\Service\VersionSelection;

final class VersionSelectionTest extends TestCase
{
    public function testVersionAndTranslationAreResolvedTogether(): void
    {
        self::assertSame([2, 'fre-CA'], (new VersionSelection())->resolve('2:fre-CA', [2 => new VersionInfo(['versionNo' => 2, 'languageCodes' => ['eng-GB', 'fre-CA']])]));
    }

    /** @dataProvider invalid */
    public function testUnavailableVersionOrLanguageNeverFallsBack(string $value, int $status): void
    {
        try {
            (new VersionSelection())->resolve($value, [2 => new VersionInfo(['versionNo' => 2, 'languageCodes' => ['eng-GB']])]);
            self::fail('Invalid selection must be rejected.');
        } catch (HttpExceptionInterface $exception) {
            self::assertSame($status, $exception->getStatusCode());
        }
    }

    public static function invalid(): iterable
    {
        yield ['other-content:eng-GB', 400];
        yield ['0:eng-GB', 400];
        yield ['9999999999999999999999:eng-GB', 400];
        yield ['2', 400];
        yield ['3:eng-GB', 404];
        yield ['2:fre-CA', 404];
    }
}
