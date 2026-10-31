<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Core\Repository\Values\Content\Content;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;

final class Fixtures
{
    public static function version(int $number, int $status = VersionInfo::STATUS_DRAFT): VersionInfo
    {
        return new VersionInfo([
            'versionNo' => $number, 'status' => $status, 'languageCodes' => ['eng-GB', 'fre-CA'],
            'initialLanguage' => new Language(['languageCode' => 'eng-GB']),
            'modificationDate' => new \DateTimeImmutable('2026-10-31'),
            'contentInfo' => new ContentInfo(['id' => 12, 'contentTypeId' => 3, 'name' => 'Article', 'mainLanguageCode' => 'eng-GB']),
        ]);
    }

    public static function content(array $fields = [], int $number = 1, array $nonTranslatable = []): Content
    {
        $definitions = [];
        foreach ($fields as $field) {
            $definitions[$field->getFieldDefinitionIdentifier()] = new FieldDefinition([
                'identifier' => $field->getFieldDefinitionIdentifier(), 'fieldTypeIdentifier' => $field->getFieldTypeIdentifier(),
                'names' => ['eng-GB' => $field->getFieldDefinitionIdentifier()],
                'isTranslatable' => !in_array($field->getFieldDefinitionIdentifier(), $nonTranslatable, true),
            ]);
        }

        return new Content([
            'versionInfo' => self::version($number), 'internalFields' => $fields,
            'contentType' => new ContentType(['id' => 3, 'identifier' => 'article', 'fieldDefinitions' => new FieldDefinitionCollection($definitions)]),
        ]);
    }

    public static function field(string $identifier, string $type, mixed $value, string $language = 'eng-GB'): Field
    {
        return new Field(['fieldDefIdentifier' => $identifier, 'fieldTypeIdentifier' => $type, 'value' => $value, 'languageCode' => $language]);
    }
}
