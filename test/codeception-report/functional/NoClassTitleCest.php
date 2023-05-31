<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Report\Functional;

use Codeception\Example;
use Qameta\Allure\Attribute\DisplayName;
use Qameta\Allure\Attribute\Issue;
use Qameta\Allure\Codeception\Test\Report\FunctionalTester;

#[Issue('Issue 1')]
class NoClassTitleCest
{
    #[DisplayName('Action title')]
    public function makeActionWithTitle(FunctionalTester $I): void
    {
        $I->expect("some condition");
    }

    /**
     * @example ["condition 1"]
     * @example {"condition":"condition 2"}
     */
    public function makeActionWithExamples(FunctionalTester $I, Example $example): void
    {
        $I->expect($example[0] ?? $example['condition']);
    }
}
