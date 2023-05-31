<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Functional;

use Codeception\Scenario;
use Qameta\Allure\Codeception\Test\Report\FunctionalTester;

/** @var Scenario $scenario */
$I = new FunctionalTester($scenario);
$I->expect('some condition');
