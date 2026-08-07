<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Hooks\Setup;

use Qameta\Allure\Allure;

/**
 * Resolves ALLURE_HOOK_OUTPUT for subprocess integration runs.
 */
final class OutputDirectoryHook
{
    public function __invoke(): void
    {
        $output = getenv('ALLURE_HOOK_OUTPUT');
        if ($output === false || $output === '') {
            return;
        }

        Allure::getLifecycleConfigurator()->setOutputDirectory($output);
    }
}
