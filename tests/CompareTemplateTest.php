<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Wizhippo\IbexaVersionComparison\Service\PreviewDocument;

final class CompareTemplateTest extends TestCase
{
    /** @dataProvider views */
    public function testPageRendersSelectorsAndSandboxedPreviewsWithoutInjectingContentIntoParent(string $view): void
    {
        $loader = new ChainLoader([
            new ArrayLoader(['@ibexadesign/ui/layout.html.twig' => '<html><head>{% block stylesheets %}{% endblock %}</head><body>{% block content %}{% endblock %}</body></html>']),
            new FilesystemLoader(dirname(__DIR__).'/templates'),
        ]);
        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('path', static fn (): string => '/test'));
        $twig->addFunction(new TwigFunction('encore_entry_link_tags', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('ibexa_full_datetime', static fn (\DateTimeInterface $date): string => $date->format('Y-m-d')));
        $preview = (new PreviewDocument())->create('<h2>Trip</h2><table><tr><td>Port</td></tr></table></iframe><script>unsafe()</script>', 'https://localhost');
        $html = $twig->render('compare.html.twig', [
            'info' => Fixtures::version(1)->getContentInfo(), 'versions' => [1 => Fixtures::version(1), 2 => Fixtures::version(2)],
            'left' => Fixtures::version(1), 'right' => Fixtures::version(2), 'leftValue' => '1:eng-GB', 'rightValue' => '2:fre-CA',
            'leftLanguage' => 'eng-GB', 'rightLanguage' => 'fre-CA', 'changed' => 1, 'changedOnly' => false, 'view' => $view,
            'fields' => [['label' => 'Body', 'type' => 'ibexa_richtext', 'status' => 'updated', 'note' => '', 'coarse' => false, 'rendered' => true, 'media' => false, 'leftPreview' => $preview, 'rightPreview' => $preview, 'unifiedPreview' => $preview]],
        ]);
        self::assertStringContainsString('name="left"', $html);
        self::assertStringContainsString('name="right"', $html);
        self::assertStringContainsString('vc-comparison--'.$view, $html);
        self::assertStringContainsString('sandbox="allow-same-origin"', $html);
        self::assertStringNotContainsString('allow-scripts', $html);
        self::assertStringNotContainsString('<script>unsafe()', $html);
        self::assertStringContainsString('&lt;script&gt;unsafe()', $html);
        self::assertStringContainsString('srcdoc="&lt;!doctype html&gt;', $html);
    }

    public static function views(): iterable
    {
        yield ['split'];
        yield ['unified'];
    }
}
