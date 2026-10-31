<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Menu;

use Ibexa\AdminUi\Menu\Action\VersionListActionMenuBuilder;
use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Wizhippo\IbexaVersionComparison\Security\VersionComparisonPolicy;

final readonly class CompareActionSubscriber implements EventSubscriberInterface
{
    public function __construct(private AuthorizationCheckerInterface $security, private PermissionResolver $permissions, private UrlGeneratorInterface $urls, private VersionComparisonPolicy $policy)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [VersionListActionMenuBuilder::class => 'configure'];
    }

    public function configure(ConfigureMenuEvent $event): void
    {
        $version = $event->getOptions()['versionInfo'] ?? null;
        if (!$version instanceof VersionInfo || ($event->getOptions()['isDraftConflict'] ?? false)
            || !$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')
            || !$this->policy->isGranted(VersionComparisonPolicy::FUNCTION_VIEW)
            || !$this->permissions->canUser('content', 'read', $version->getContentInfo())
            || !$this->permissions->canUser('content', 'versionread', $version)) {
            return;
        }
        $event->getMenu()->addChild('wizhippo_ibexa_version_comparison_compare', [
            'label' => 'Compare versions',
            'uri' => $this->urls->generate('wizhippo_ibexa_version_comparison_compare', ['contentId' => $version->getContentInfo()->getId(), 'right' => $version->getVersionNo().':'.$version->getInitialLanguage()->getLanguageCode()]),
            'extras' => ['icon' => 'action-compare-versions', 'orderNumber' => 20, 'translation_domain' => false],
        ]);
    }
}
