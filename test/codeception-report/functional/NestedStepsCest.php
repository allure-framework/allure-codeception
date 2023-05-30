<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Report\Functional;

use Qameta\Allure\Allure;
use Qameta\Allure\Attribute\DisplayName;
use Qameta\Allure\Codeception\Test\Report\FunctionalTester;

#[DisplayName('Nested steps')]
class NestedStepsCest
{
    public function makeNestedSteps(FunctionalTester $I): void
    {
        Allure::runStep(
            function () use ($I): void {
                $I->expect("condition 1");
                Allure::runStep(
                    function () use ($I): void {
                        $I->expect("condition 1.1");
                        Allure::runStep(
                            function () use ($I): void {
                                $I->expect("condition 1.1.1");
                            },
                            'Step 1.1.1',
                        );
                    },
                    'Step 1.1',
                );
                Allure::runStep(
                    function () use ($I): void {
                        $I->expect("condition 1.2");
                    },
                    'Step 1.2',
                );
            },
            'Step 1',
        );
    }
}
