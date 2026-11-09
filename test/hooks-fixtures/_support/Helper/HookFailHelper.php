<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Hooks\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Module hooks controlled by ALLURE_HOOK_FAIL / ALLURE_HOOK_MODE env vars.
 *
 * Aggregate Module phase only — cannot split Module vs @beforeClass via extension.
 */
final class HookFailHelper extends Module
{
    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    public function _beforeSuite(array $settings = []): void
    {
        $this->maybeFail('_beforeSuite');
    }

    public function _afterSuite(): void
    {
        $this->maybeFail('_afterSuite');
    }

    public function _before(TestInterface $test): void
    {
        $this->maybeFail('_before');
    }

    public function _after(TestInterface $test): void
    {
        $this->maybeFail('_after');
    }
    // phpcs:enable PSR2.Methods.MethodDeclaration.Underscore

    private function maybeFail(string $hookName): void
    {
        $target = getenv('ALLURE_HOOK_FAIL');
        if ($target !== $hookName) {
            return;
        }

        $mode = getenv('ALLURE_HOOK_MODE') ?: 'throw';
        match ($mode) {
            'assert' => Assert::fail("{$hookName} assertion"),
            'empty' => throw new RuntimeException(''),
            default => throw new RuntimeException("{$hookName} boom"),
        };
    }
}
