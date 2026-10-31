<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison;

use Ibexa\Bundle\Core\DependencyInjection\IbexaCoreExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Wizhippo\IbexaVersionComparison\Security\PolicyProvider;

final class WizhippoIbexaVersionComparisonBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        /** @var IbexaCoreExtension $ibexa */
        $ibexa = $container->getExtension('ibexa');
        $ibexa->addPolicyProvider(new PolicyProvider());
    }
}
