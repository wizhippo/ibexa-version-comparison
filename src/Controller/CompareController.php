<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Controller;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Repository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wizhippo\IbexaVersionComparison\Security\VersionComparisonPolicy;
use Wizhippo\IbexaVersionComparison\Service\FieldComparison;
use Wizhippo\IbexaVersionComparison\Service\VersionSelection;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class CompareController extends AbstractController
{
    public function __construct(private readonly VersionComparisonPolicy $policy)
    {
    }

    #[Route('/version-comparison/{contentId}', name: 'wizhippo_ibexa_version_comparison_compare', requirements: ['contentId' => '[1-9][0-9]{0,9}'], methods: ['GET'])]
    public function compare(int $contentId, Request $request, Repository $repository, VersionSelection $selection, FieldComparison $comparison): Response
    {
        if (!$this->policy->isGranted(VersionComparisonPolicy::FUNCTION_VIEW)) {
            throw $this->createAccessDeniedException();
        }

        $view = $request->query->getString('view', 'split');
        if (!in_array($view, ['split', 'unified'], true)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Unknown comparison view.');
        }
        try {
            $service = $repository->getContentService();
            $info = $service->loadContentInfo($contentId);
            $versions = [];
            foreach ($service->loadVersions($info) as $version) {
                $versions[$version->getVersionNo()] = $version;
            }
            krsort($versions);
            if ($versions === []) {
                throw $this->createNotFoundException('No versions are available.');
            }
            $newest = reset($versions);
            $rightValue = $request->query->getString('right', $newest->getVersionNo().':'.$newest->getInitialLanguage()->getLanguageCode());
            [$rightNumber, $rightLanguage] = $selection->resolve($rightValue, $versions);
            $base = null;
            foreach ($versions as $version) {
                if ($version->isPublished() && $version->getVersionNo() !== $rightNumber) {
                    $base = $version;
                    break;
                }
            }
            if ($base === null) {
                foreach ($versions as $version) {
                    if ($version->getVersionNo() !== $rightNumber) {
                        $base = $version;
                        break;
                    }
                }
            }
            $base ??= $versions[$rightNumber];
            $baseLanguage = in_array($rightLanguage, $base->getLanguageCodes(), true) ? $rightLanguage : $base->getInitialLanguage()->getLanguageCode();
            $leftValue = $request->query->getString('left', $base->getVersionNo().':'.$baseLanguage);
            [$leftNumber, $leftLanguage] = $selection->resolve($leftValue, $versions);
            // Exact versions, no language fallback, and no sudo: native read/versionread
            // limitations are enforced again for each selected content version.
            $left = $service->loadContent($contentId, null, $leftNumber, false);
            $right = $service->loadContent($contentId, null, $rightNumber, false);
            $permission = $repository->getPermissionResolver();
            foreach ([$left, $right] as $content) {
                if (!$permission->canUser('content', 'read', $content) || !$permission->canUser('content', 'versionread', $content)) {
                    throw $this->createAccessDeniedException('This content version is unavailable.');
                }
            }
            $fields = $comparison->compare($left, $leftLanguage, $right, $rightLanguage, $request->getSchemeAndHttpHost());
        } catch (NotFoundException) {
            throw $this->createNotFoundException('This content version is unavailable.');
        } catch (UnauthorizedException) {
            throw $this->createAccessDeniedException('This content version is unavailable.');
        }
        $changed = count(array_filter($fields, static fn (array $field): bool => !in_array($field['status'], ['unchanged', 'unsupported'], true)));
        $changedOnly = $request->query->getBoolean('changedOnly');
        if ($changedOnly) {
            $fields = array_filter($fields, static fn (array $field): bool => $field['status'] !== 'unchanged');
        }

        return $this->render('@WizhippoIbexaVersionComparison/compare.html.twig', [
            'info' => $info, 'versions' => $versions, 'left' => $versions[$leftNumber], 'right' => $versions[$rightNumber],
            'leftValue' => $leftValue, 'rightValue' => $rightValue, 'leftLanguage' => $leftLanguage, 'rightLanguage' => $rightLanguage,
            'fields' => $fields, 'changed' => $changed, 'view' => $view, 'changedOnly' => $changedOnly,
        ], new Response(headers: ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex']));
    }
}
