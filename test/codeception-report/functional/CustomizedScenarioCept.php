<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Report\Functional;

use Codeception\Scenario;
use Qameta\Allure\Allure;
use Qameta\Allure\Codeception\Test\Report\FunctionalTester;

Allure::displayName('Scenario title');
Allure::description('Description with *markdown*');
Allure::issue('Issue 1');
Allure::feature('Feature 1');
Allure::story('Story 1');

/** @var Scenario $scenario */
$I = new FunctionalTester($scenario);
$I->expect('some condition');
