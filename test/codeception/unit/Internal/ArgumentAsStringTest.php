<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit\Internal;

use Codeception\Test\Unit;
use Qameta\Allure\Codeception\Internal\ArgumentAsString;

class ArgumentAsStringTest extends Unit
{
    /**
     * @dataProvider providerString
     */
    public function testString(string $argument, string $expectedValue): void
    {
        self::assertSame($expectedValue, ArgumentAsString::get($argument));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerString(): iterable
    {
        return [
            'Simple string' => ['a', '"a"'],
            'String with tabulation' => ["a\tb", '"a b"'],
            'String with line feed' => ["a\nb", '"a\\\\nb"'],
            'String with carriage return' => ["a\rb", '"a\\\\rb"'],
        ];
    }
}
