<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Service;

use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Psr\Log\LoggerInterface;
use Twig\Environment;

final class FieldComparison
{
    private const array SUPPORTED = ['ibexa_string', 'ibexa_text', 'ibexa_richtext', 'ibexa_integer', 'ibexa_float', 'ibexa_boolean', 'ibexa_date', 'ibexa_datetime', 'ibexa_time', 'ibexa_url', 'ibexa_email', 'ibexa_author', 'ibexa_keyword', 'ibexa_selection', 'ibexa_country', 'ibexa_object_relation', 'ibexa_object_relation_list', 'ibexa_image', 'ibexa_image_asset', 'ibexa_binaryfile', 'ibexa_media', 'ibexa_isbn'];
    private const array RENDERED = ['ibexa_richtext', 'ibexa_image', 'ibexa_image_asset', 'ibexa_media'];
    private const array MEDIA = ['ibexa_image', 'ibexa_image_asset', 'ibexa_media'];

    public function __construct(
        private readonly FieldTypeService $types,
        private readonly Environment $twig,
        private readonly TokenDiff $textDiff,
        private readonly HtmlDiff $htmlDiff,
        private readonly PreviewDocument $preview,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function compare(Content $left, string $leftLanguage, Content $right, string $rightLanguage, string $origin): array
    {
        $definitions = [];
        foreach ($right->getContentType()->getFieldDefinitions() as $definition) {
            $definitions[$definition->getIdentifier()] = $definition;
        }
        $identifiers = array_keys($definitions);
        foreach ([$left->getFieldsByLanguage($leftLanguage), $right->getFieldsByLanguage($rightLanguage)] as $fields) {
            foreach ($fields as $field) {
                $identifiers[] = $field->getFieldDefinitionIdentifier();
            }
        }
        $result = [];
        foreach (array_unique($identifiers) as $identifier) {
            $definition = $definitions[$identifier] ?? null;
            $oldLanguage = $leftLanguage;
            $newLanguage = $rightLanguage;
            if ($definition !== null && !$definition->isTranslatable()) {
                $oldLanguage = $left->getVersionInfo()->getInitialLanguage()->getLanguageCode();
                $newLanguage = $right->getVersionInfo()->getInitialLanguage()->getLanguageCode();
            }
            $old = $left->getField($identifier, $oldLanguage);
            $new = $right->getField($identifier, $newLanguage);
            $type = $new?->getFieldTypeIdentifier() ?? $old?->getFieldTypeIdentifier() ?? $definition?->getFieldTypeIdentifier();
            $row = ['identifier' => $identifier, 'label' => $definition?->getName($rightLanguage) ?? $identifier, 'type' => $type, 'status' => 'unsupported', 'note' => '', 'rendered' => false, 'media' => false];
            if (!in_array($type, self::SUPPORTED, true) || ($old !== null && !in_array($old->getFieldTypeIdentifier(), self::SUPPORTED, true))) {
                $row['note'] = 'Comparison is unavailable for this field type.';
                $result[] = $row;

                continue;
            }
            try {
                $before = $this->snapshot($left, $old, $oldLanguage, $definition !== null);
                $after = $this->snapshot($right, $new, $newLanguage, $definition !== null);
                $row['status'] = $old === null && $new !== null ? 'added' : ($new === null && $old !== null ? 'removed' : ($before['canonical'] === $after['canonical'] ? 'unchanged' : 'updated'));
                $row['note'] = trim($before['note'].' '.$after['note']);
                $row['media'] = in_array($type, self::MEDIA, true);
                $row['rendered'] = in_array($type, self::RENDERED, true) && $before['rendered'] && $after['rendered'];
                $diff = $this->textDiff->text($before['text'], $after['text']);
                $row += ['leftText' => $diff['leftHtml'], 'rightText' => $diff['rightHtml'], 'unifiedText' => $diff['unified'], 'coarse' => $diff['coarse']];
                if ($row['rendered']) {
                    $html = $this->htmlDiff->compare($before['html'], $after['html']);
                    $row['leftPreview'] = $this->preview->create($html['left'], $origin);
                    $row['rightPreview'] = $this->preview->create($html['right'], $origin);
                    $row['unifiedPreview'] = $this->preview->create($new === null ? $html['left'] : $html['unified'], $origin);
                    $row['coarse'] = $html['coarse'];
                    if ($row['status'] === 'updated') {
                        $row['note'] .= ' Formatting, references, media, or field settings may also differ; compare the rendered previews.';
                    }
                }
            } catch (\Throwable $exception) {
                $this->logger->warning('Version comparison field unavailable.', ['field' => $identifier, 'exception_class' => $exception::class]);
                $row['status'] = 'unsupported';
                $row['note'] = 'This field could not be compared. Other fields remain available.';
            }
            $result[] = $row;
        }

        return $result;
    }

    private function snapshot(Content $content, ?Field $field, string $language, bool $hasDefinition): array
    {
        if ($field === null) {
            return ['canonical' => null, 'text' => '', 'html' => '', 'rendered' => true, 'note' => ''];
        }
        $type = $field->getFieldTypeIdentifier();
        $value = $field->getValue();
        $hash = $this->types->getFieldType($type)->toHash($value);
        if ($type === 'ibexa_image' && is_array($hash)) {
            // Ibexa's image valuesEqual() ignores this per-version bookkeeping ID.
            unset($hash['imageId']);
        }
        $canonical = json_encode($this->sort($hash), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($canonical) > 262144) {
            throw new \LengthException('Field value exceeds the comparison limit.');
        }
        $text = is_string($hash) ? $hash : json_encode($this->sort($hash), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($type === 'ibexa_richtext' && $value instanceof \Ibexa\FieldTypeRichText\FieldType\RichText\Value) {
            $canonical = $value->xml->C14N();
            $text = $value->xml->textContent;
        }
        $snapshot = ['canonical' => $type.':'.$canonical, 'text' => $text, 'html' => '', 'rendered' => false, 'note' => ''];
        if (in_array($type, self::RENDERED, true) && $hasDefinition) {
            try {
                $snapshot['html'] = $this->twig->render('@WizhippoIbexaVersionComparison/field.html.twig', ['content' => $content, 'identifier' => $field->getFieldDefinitionIdentifier(), 'language' => $language]);
                $snapshot['rendered'] = true;
            } catch (\Throwable $exception) {
                $this->logger->warning('Version comparison preview unavailable.', ['field' => $field->getFieldDefinitionIdentifier(), 'exception_class' => $exception::class]);
                $snapshot['note'] = 'Native preview unavailable; a text or metadata comparison is shown.';
            }
        }

        return $snapshot;
    }

    private function sort(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->sort(...), $value);
    }
}
