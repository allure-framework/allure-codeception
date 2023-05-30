<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit\Internal;

use Codeception\Test\Cest;
use Codeception\Test\Unit;
use Qameta\Allure\Codeception\Internal\CestProvider;

class CestProviderTest extends Unit
{
    public function testFullName(): void
    {
        $cest = new Cest($this, 'a', 'b');
        $cestProvider = new CestProvider($cest);

        $this->assertSame(__CLASS__ . '::' . 'a', $cestProvider->getFullName());
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function a(): void
    {
    }
}
