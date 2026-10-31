<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use Ibexa\Contracts\Core\Repository\FieldType;
use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Core\FieldType\TextLine\Value;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Twig\Environment;
use Wizhippo\IbexaVersionComparison\Service\FieldComparison;
use Wizhippo\IbexaVersionComparison\Service\HtmlDiff;
use Wizhippo\IbexaVersionComparison\Service\PreviewDocument;
use Wizhippo\IbexaVersionComparison\Service\TokenDiff;

final class FieldComparisonTest extends TestCase
{
    public function testMissingFieldsAndChangedValuesRemainDistinctFromEmptyValues(): void
    {
        $old = Fixtures::content([Fixtures::field('title', 'ibexa_string', new Value('Old title')), Fixtures::field('removed', 'ibexa_string', new Value('Old'))]);
        $new = Fixtures::content([Fixtures::field('title', 'ibexa_string', new Value('New title')), Fixtures::field('added', 'ibexa_string', new Value('New'))], 2);
        $rows = array_column($this->service()->compare($old, 'eng-GB', $new, 'eng-GB', 'https://localhost'), null, 'identifier');
        self::assertSame('updated', $rows['title']['status']);
        self::assertSame('added', $rows['added']['status']);
        self::assertSame('removed', $rows['removed']['status']);
        self::assertStringContainsString('vc-added', $rows['title']['rightText']);
    }

    public function testSensitiveAndUnknownFieldsAreNeverSerializedOrRendered(): void
    {
        $types = $this->createMock(FieldTypeService::class);
        $types->expects(self::never())->method('getFieldType');
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');
        $content = Fixtures::content([Fixtures::field('account', 'ibexa_user', new Value('password-hash-secret'))]);
        $rows = $this->service($types, $twig)->compare($content, 'eng-GB', $content, 'eng-GB', 'https://localhost');
        self::assertSame('unsupported', $rows[0]['status']);
        self::assertStringNotContainsString('password-hash-secret', json_encode($rows));
    }

    public function testUnavailableTranslationIsNotSilentlySubstituted(): void
    {
        $content = Fixtures::content([Fixtures::field('title', 'ibexa_string', new Value('English'))]);
        $rows = $this->service()->compare($content, 'eng-GB', $content, 'fre-CA', 'https://localhost');
        self::assertSame('removed', $rows[0]['status']);
        self::assertSame('', $rows[0]['rightText']);
    }

    public function testNonTranslatableFieldsUseTheVersionsInitialLanguage(): void
    {
        $content = Fixtures::content([Fixtures::field('title', 'ibexa_string', new Value('Shared'))], 1, ['title']);
        $rows = $this->service()->compare($content, 'eng-GB', $content, 'fre-CA', 'https://localhost');
        self::assertSame('unchanged', $rows[0]['status']);
    }

    public function testImagesRetainNativePreviewsAndIgnoreBookkeepingIds(): void
    {
        $types = $this->createMock(FieldTypeService::class);
        $type = $this->createMock(FieldType::class);
        $type->method('toHash')->willReturnCallback(static fn ($value): array => ['uri' => $value->uri, 'imageId' => $value->imageId]);
        $types->method('getFieldType')->willReturn($type);
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static fn ($template, array $context): string => '<img src="'.$context['content']->getField('image', 'eng-GB')->getValue()->uri.'" alt="Ship">');
        $old = Fixtures::content([Fixtures::field('image', 'ibexa_image', new \Ibexa\Core\FieldType\Image\Value(['uri' => '/same.jpg', 'imageId' => 'old']))]);
        $new = Fixtures::content([Fixtures::field('image', 'ibexa_image', new \Ibexa\Core\FieldType\Image\Value(['uri' => '/same.jpg', 'imageId' => 'new']))], 2);
        $service = $this->service($types, $twig);
        $rows = $service->compare($old, 'eng-GB', $new, 'eng-GB', 'https://localhost');
        self::assertSame('unchanged', $rows[0]['status']);
        self::assertTrue($rows[0]['media']);
        self::assertStringContainsString('src="/same.jpg"', $rows[0]['leftPreview']);
        $different = Fixtures::content([Fixtures::field('image', 'ibexa_image', new \Ibexa\Core\FieldType\Image\Value(['uri' => '/new.jpg', 'imageId' => 'new']))], 2);
        $rows = $service->compare($old, 'eng-GB', $different, 'eng-GB', 'https://localhost');
        self::assertSame('updated', $rows[0]['status']);
        self::assertStringContainsString('src="/new.jpg"', $rows[0]['rightPreview']);
    }

    private function service(?FieldTypeService $types = null, ?Environment $twig = null): FieldComparison
    {
        if ($types === null) {
            $types = $this->createMock(FieldTypeService::class);
            $type = $this->createMock(FieldType::class);
            $type->method('toHash')->willReturnCallback(static fn ($value): string => (string)$value);
            $types->method('getFieldType')->willReturn($type);
        }
        $diff = new TokenDiff();

        return new FieldComparison($types, $twig ?? $this->createMock(Environment::class), $diff, new HtmlDiff($diff), new PreviewDocument(), new NullLogger());
    }
}
