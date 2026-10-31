<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;
use Wizhippo\IbexaVersionComparison\Controller\CompareController;
use Wizhippo\IbexaVersionComparison\Security\VersionComparisonPolicy;
use Wizhippo\IbexaVersionComparison\Service\FieldComparison;
use Wizhippo\IbexaVersionComparison\Service\HtmlDiff;
use Wizhippo\IbexaVersionComparison\Service\PreviewDocument;
use Wizhippo\IbexaVersionComparison\Service\TokenDiff;
use Wizhippo\IbexaVersionComparison\Service\VersionSelection;

final class CompareControllerTest extends TestCase
{
    public function testExactVersionsAreLoadedReadOnlyAndResponseIsNotCached(): void
    {
        [$controller, $repo, $fields] = $this->fixture(true);
        $response = $controller->compare(12, Request::create('https://localhost/version-comparison/12?left=1:eng-GB&right=2:fre-CA'), $repo, new VersionSelection(), $fields);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function testVersionPermissionDenialStopsComparison(): void
    {
        [$controller, $repo, $fields] = $this->fixture(false);
        $this->expectException(AccessDeniedException::class);
        $controller->compare(12, Request::create('/version-comparison/12'), $repo, new VersionSelection(), $fields);
    }

    public function testAnUnlistedVersionIsNotLoaded(): void
    {
        [$controller, $repo, $fields] = $this->fixture(true, false);
        $this->expectException(NotFoundHttpException::class);
        $controller->compare(12, Request::create('/version-comparison/12?right=999:eng-GB'), $repo, new VersionSelection(), $fields);
    }

    public function testFeaturePermissionDenialStopsRepositoryAccess(): void
    {
        $container = new Container();
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->expects(self::once())->method('isGranted')
            ->with(new Attribute(VersionComparisonPolicy::MODULE, VersionComparisonPolicy::FUNCTION_VIEW))
            ->willReturn(false);
        $controller = new CompareController(new VersionComparisonPolicy($security));
        $controller->setContainer($container);
        $repository = $this->createMock(Repository::class);
        $repository->expects(self::never())->method('getContentService');
        [, , $fields] = $this->fixture(true, false);

        $this->expectException(AccessDeniedException::class);
        $controller->compare(12, Request::create('/version-comparison/12'), $repository, new VersionSelection(), $fields);
    }

    private function fixture(bool $allowed, bool $loads = true): array
    {
        $repo = $this->createMock(Repository::class);
        $content = $this->createMock(ContentService::class);
        $content->method('loadContentInfo')->with(12)->willReturn(Fixtures::version(1)->getContentInfo());
        $content->method('loadVersions')->willReturn([Fixtures::version(1, VersionInfo::STATUS_PUBLISHED), Fixtures::version(2)]);
        if ($loads) {
            $content->expects(self::exactly(2))->method('loadContent')->willReturnCallback(static function (int $id, ?array $languages, ?int $version, bool $fallback) {
                self::assertSame(12, $id);
                self::assertNull($languages);
                self::assertFalse($fallback);
                self::assertContains($version, [1, 2]);

                return Fixtures::content([], $version);
            });
        } else {
            $content->expects(self::never())->method('loadContent');
        }
        $content->expects(self::never())->method('updateContent');
        $content->expects(self::never())->method('publishVersion');
        $repo->method('getContentService')->willReturn($content);
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('canUser')->willReturnCallback(static fn (string $module, string $function): bool => $function === 'read' || $allowed);
        $repo->method('getPermissionResolver')->willReturn($permissions);
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('Comparison');
        $container = new Container();
        $container->set('twig', $twig);
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->method('isGranted')
            ->with(new Attribute(VersionComparisonPolicy::MODULE, VersionComparisonPolicy::FUNCTION_VIEW))
            ->willReturn(true);
        $controller = new CompareController(new VersionComparisonPolicy($security));
        $controller->setContainer($container);
        $diff = new TokenDiff();
        $fields = new FieldComparison($this->createMock(FieldTypeService::class), $twig, $diff, new HtmlDiff($diff), new PreviewDocument(), new NullLogger());

        return [$controller, $repo, $fields];
    }
}
