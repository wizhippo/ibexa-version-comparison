<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Security;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\ConfigBuilderInterface;
use Ibexa\Bundle\Core\DependencyInjection\Security\PolicyProvider\PolicyProviderInterface;

final class PolicyProvider implements PolicyProviderInterface
{
    public function addPolicies(ConfigBuilderInterface $builder): void
    {
        $builder->addConfig([VersionComparisonPolicy::MODULE => [VersionComparisonPolicy::FUNCTION_VIEW => null]]);
    }
}
