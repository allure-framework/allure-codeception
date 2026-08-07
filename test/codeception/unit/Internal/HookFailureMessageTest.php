<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit\Internal;

use Codeception\Test\Unit;
use Qameta\Allure\Codeception\Internal\HookFailureMessage;

final class HookFailureMessageTest extends Unit
{
    /**
     * @dataProvider providerFormat
     */
    public function testFormat_MessageVariants_ReturnsPrefixedOrFallback(
        string $hookName,
        ?string $original,
        string $expected,
    ): void {
        self::assertSame($expected, HookFailureMessage::format($hookName, $original));
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function providerFormat(): iterable
    {
        yield 'with details' => ['_before', 'boom', '_before failed: boom'];
        yield 'empty string' => ['_before', '', '_before failed'];
        yield 'whitespace only' => ['_after', "  \t  ", '_after failed'];
        yield 'null' => ['_beforeSuite', null, '_beforeSuite failed'];
        yield 'assertion message' => [
            '_before',
            'Failed asserting that false is true.',
            '_before failed: Failed asserting that false is true.',
        ];
    }
}
