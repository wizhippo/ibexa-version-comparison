<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Security;

use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** Bundle-scoped Ibexa policies. */
final readonly class VersionComparisonPolicy
{
    public const string MODULE = 'wizhippo_ibexa_version_comparison';

    public const string FUNCTION_VIEW = 'view';

    public function __construct(private AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public function isGranted(string $function): bool
    {
        return $this->authorizationChecker->isGranted(new Attribute(self::MODULE, $function));
    }
}
