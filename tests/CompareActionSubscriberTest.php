<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Wizhippo\IbexaVersionComparison\Menu\CompareActionSubscriber;
use Wizhippo\IbexaVersionComparison\Security\VersionComparisonPolicy;

final class CompareActionSubscriberTest extends TestCase
{
    /** @dataProvider permissions */
    public function testVersionActionHonoursFeatureAndRepositoryPermissions(bool $policy, bool $readable, bool $conflict, bool $visible): void
    {
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->method('isGranted')->willReturnCallback(static function (mixed $attribute) use ($policy): bool {
            if ($attribute === 'IS_AUTHENTICATED_REMEMBERED') {
                return true;
            }
            self::assertEquals(new Attribute(VersionComparisonPolicy::MODULE, VersionComparisonPolicy::FUNCTION_VIEW), $attribute);

            return $policy;
        });
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('canUser')->willReturn($readable);
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects($visible ? self::once() : self::never())->method('generate')
            ->with('wizhippo_ibexa_version_comparison_compare', ['contentId' => 12, 'right' => '2:eng-GB'])
            ->willReturn('/version-comparison/12?right=2%3Aeng-GB');
        $factory = new MenuFactory();
        $menu = $factory->createItem('root');
        $event = new ConfigureMenuEvent($factory, $menu, ['versionInfo' => Fixtures::version(2), 'isDraftConflict' => $conflict]);
        (new CompareActionSubscriber($security, $permissions, $urls, new VersionComparisonPolicy($security)))->configure($event);
        self::assertSame($visible, $menu->getChild('wizhippo_ibexa_version_comparison_compare') !== null);
    }

    public static function permissions(): iterable
    {
        yield 'available' => [true, true, false, true];
        yield 'no feature permission' => [false, true, false, false];
        yield 'no repository permission' => [true, false, false, false];
        yield 'draft conflict modal' => [true, true, true, false];
    }
}
