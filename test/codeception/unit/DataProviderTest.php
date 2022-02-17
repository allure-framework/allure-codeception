<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit;

use Codeception\Test\Unit;
use Qameta\Allure\Attribute;

class DataProviderTest extends Unit
{
    /**
     * @dataProvider providerData
     */
    public function testDataProviderWithoutTitle(string $first, string $second): void
    {
        self::assertSame($first, $second);
    }

    /**
     * @dataProvider providerData
     */
    #[Attribute\DisplayName('Data title')]
    public function testDataProviderWithTitle(string $first, string $second): void
    {
        self::assertSame($first, $second);
    }

    /**
     * @return iterable<int|string, array{string, string}>
     */
    public function providerData(): iterable
    {
        return [
            0 => ['foo', 'foo'],
            'a' => ['bar', 'bar'],
            'b' => ['foo', 'bar'],
        ];
    }
}
