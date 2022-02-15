<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Functional;

use Qameta\Allure\Attribute\DisplayName;
use Qameta\Allure\Codeception\Test\FunctionalTester;

#[DisplayName('Cest Title')]
class ClassTitleCest
{
    public function makeAction(FunctionalTester $I): void
    {
        $I->expect('some condition');
    }
}
