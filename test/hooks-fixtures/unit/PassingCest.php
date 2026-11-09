<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Hooks;

use PHPUnit\Framework\Assert;

/**
 * Minimal passing body used by hook-failure subprocess suites.
 *
 * Not covered by extension events (documented exclusion):
 * - Cest `_before` / `_after` / `@before` / `@after` (inside Cest::test())
 * - Unit setUp/tearDown (inside PHPUnit case)
 * - Step hooks / Module `_failed`
 */
final class PassingCest
{
    public function testPass(UnitTester $I): void
    {
        Assert::assertTrue(true);
    }
}
