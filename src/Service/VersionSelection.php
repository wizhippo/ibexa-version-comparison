<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Service;

use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class VersionSelection
{
    /** @param array<int, VersionInfo> $versions */
    public function resolve(string $selection, array $versions): array
    {
        $parts = explode(':', $selection, 2);
        if (count($parts) !== 2 || !preg_match('/^[1-9][0-9]*$/D', $parts[0]) || filter_var($parts[0], FILTER_VALIDATE_INT) === false) {
            throw new BadRequestHttpException('Select a valid version and language.');
        }
        $number = (int)$parts[0];
        if (!isset($versions[$number]) || !in_array($parts[1], $versions[$number]->getLanguageCodes(), true)) {
            throw new NotFoundHttpException('The selected version or translation is unavailable.');
        }

        return [$number, $parts[1]];
    }
}
