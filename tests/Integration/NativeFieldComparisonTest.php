<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests\Integration;

use Ibexa\FieldTypeRichText\FieldType\RichText\Value;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wizhippo\IbexaVersionComparison\Security\VersionComparisonPolicy;
use Wizhippo\IbexaVersionComparison\Service\FieldComparison;
use Wizhippo\IbexaVersionComparison\Tests\Fixtures;

final class NativeFieldComparisonTest extends KernelTestCase
{
    public function testNativeRelationFieldsAreCompared(): void
    {
        $service = self::getContainer()->get(FieldComparison::class);
        foreach ([
            'ibexa_object_relation' => [new \Ibexa\Core\FieldType\Relation\Value(12), new \Ibexa\Core\FieldType\Relation\Value(34)],
            'ibexa_object_relation_list' => [new \Ibexa\Core\FieldType\RelationList\Value([12]), new \Ibexa\Core\FieldType\RelationList\Value([12, 34])],
        ] as $type => [$before, $after]) {
            $old = Fixtures::content([Fixtures::field('relation', $type, $before)]);
            $new = Fixtures::content([Fixtures::field('relation', $type, $after)], 2);
            $rows = $service->compare($old, 'eng-GB', $new, 'eng-GB', 'https://localhost');
            self::assertSame('updated', $rows[0]['status'], $type);
            self::assertStringContainsString('34', $rows[0]['rightText']);
            self::assertFalse($rows[0]['rendered']);
            $rows = $service->compare($old, 'eng-GB', $old, 'eng-GB', 'https://localhost');
            self::assertSame('unchanged', $rows[0]['status'], $type);
        }
    }

    public function testBundleRoutesAndTemplatesAreDiscovered(): void
    {
        $container = self::getContainer();
        $router = $container->get('router');
        $route = $router->getRouteCollection()->get('wizhippo_ibexa_version_comparison_compare');
        self::assertNotNull($route);
        self::assertSame(['GET'], $route->getMethods());
        self::assertSame('/version-comparison/12', $router->generate('wizhippo_ibexa_version_comparison_compare', ['contentId' => 12]));
        self::assertTrue($container->get('twig')->getLoader()->exists('@WizhippoIbexaVersionComparison/compare.html.twig'));
    }

    public function testNativeRichTextRenderingAndInlineDiffPreserveTables(): void
    {
        $oldXml = '<section xmlns="http://docbook.org/ns/docbook" xmlns:ezxhtml="http://ibexa.co/xmlns/dxp/docbook/xhtml" version="5.0-variant ezpublish-1.0"><title ezxhtml:level="2">Itinerary</title><informaltable><tbody><tr><td><para>Old port</para></td></tr></tbody></informaltable></section>';
        $newXml = str_replace('Old port', 'New port', $oldXml);
        $old = Fixtures::content([Fixtures::field('body', 'ibexa_richtext', new Value($oldXml))]);
        $new = Fixtures::content([Fixtures::field('body', 'ibexa_richtext', new Value($newXml))], 2);
        $rendered = self::getContainer()->get('twig')->render('@WizhippoIbexaVersionComparison/field.html.twig', ['content' => $old, 'identifier' => 'body', 'language' => 'eng-GB']);
        self::assertStringContainsString('<table', $rendered);
        self::assertArrayHasKey(VersionComparisonPolicy::FUNCTION_VIEW, self::getContainer()->getParameter('ibexa.api.role.policy_map')[VersionComparisonPolicy::MODULE]);
        $service = self::getContainer()->get(FieldComparison::class);
        $rows = $service->compare($old, 'eng-GB', $new, 'eng-GB', 'https://localhost');
        self::assertSame('updated', $rows[0]['status']);
        self::assertTrue($rows[0]['rendered'], $rows[0]['note']);
        self::assertStringContainsString('<table', $rows[0]['leftPreview']);
        self::assertStringContainsString('<h2', $rows[0]['rightPreview']);
        self::assertStringContainsString('class="vc-added">New</ins>', $rows[0]['rightPreview']);
        self::assertStringContainsString('class="vc-removed">Old</del>', $rows[0]['unifiedPreview']);
        self::assertSame($oldXml, $old->getField('body', 'eng-GB')->getValue()->xml->saveXML($old->getField('body', 'eng-GB')->getValue()->xml->documentElement));
    }
}
