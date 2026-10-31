<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Tests;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Voter\CoreVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Wizhippo\IbexaVersionComparison\Security\VersionComparisonPolicy;

final class PolicyProviderTest extends TestCase
{
    /** @dataProvider permissions */
    public function testNativeIbexaVoterResolvesBundlePolicy(bool $allowed, int $expected): void
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->expects(self::once())->method('hasAccess')
            ->with(VersionComparisonPolicy::MODULE, VersionComparisonPolicy::FUNCTION_VIEW)
            ->willReturn($allowed);
        $voter = new CoreVoter($permissions);

        self::assertSame($expected, $voter->vote($this->createMock(TokenInterface::class), null, [
            new Attribute(VersionComparisonPolicy::MODULE, VersionComparisonPolicy::FUNCTION_VIEW),
        ]));
    }

    public static function permissions(): iterable
    {
        yield 'granted' => [true, VoterInterface::ACCESS_GRANTED];
        yield 'denied' => [false, VoterInterface::ACCESS_DENIED];
    }
}
