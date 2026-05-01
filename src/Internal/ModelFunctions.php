<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use function array_filter;
use function array_shift;
use function explode;
use function getcwd;
use function is_string;
use function rtrim;

use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
final class ModelFunctions
{
    /**
     * @return list<string>
     */
    public static function getTitlePathByFile(string $base, string $path, bool $final = false): array
    {
        if (!$path) {
            return [];
        }

        $baseParts = explode(DIRECTORY_SEPARATOR, rtrim($base, DIRECTORY_SEPARATOR));
        $pathParts = explode(DIRECTORY_SEPARATOR, $path);

        if (!$base || $baseParts[0] !== $pathParts[0]) {
            // The base is not provided or is on another disk (on Windows)
            // or is not an absolute path.
            // Fallback to CWD if not yet in fallback mode
            if (!$final) {
                $cwd = getcwd();
                if ($cwd !== false) {
                    return self::getTitlePathByFile($cwd, $path, true);
                }
            }

            // CWD didn't work too. Turn the absolute path into titlePath.
            // Add leading '/' node on Linux/MAC to avoid confusion with
            // well-formed titlePath values of other tests.
            return $pathParts[0]
                ? $pathParts
                : [DIRECTORY_SEPARATOR, ...$pathParts];
        }

        do {
            // Skipping identical parts of both paths.
            array_shift($baseParts);
            array_shift($pathParts);
        } while ($baseParts && $pathParts && $baseParts[0] === $pathParts[0]);

        if (!$pathParts) {
            // If the path contains less parts than the base (is a parent of the base)
            // or is equal to the base, return empty titlePath.
            return [];
        }

        // At this point we have three cases:
        //   - $pathParts is empty: the path contains less parts than the base (is a
        //     parent of the base) or is equal to the base, return empty titlePath.
        //   - $baseParts is empty: the path is inside the base.
        //     The titlePath consists entirely of the remaining path parts.
        //   - $baseParts is not empty: the path is not in the base but they share a
        //     common ancestor. We consider this ancestor the proper root directory
        //     and return the remaining parts of the path as titlePath.
        // Essentially, all three cases are treated identically.
        return $pathParts;
    }

    /**
     * @return list<string>
     */
    public static function getTitlePathByClass(?string $class): array
    {
        return is_string($class)
            ? [...array_filter(explode("\\", $class))]
            : [];
    }
}
