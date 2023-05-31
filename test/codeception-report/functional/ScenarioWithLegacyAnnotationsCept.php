<?php

/**
 * @Title("Legacy scenario title")
 * @Description("Legacy description with *markdown*")
 * @Features("Feature 1","Feature 2")
 * @Stories("Story 1","Story 2")
 * @Issues("Issue 1","Issue 2")
 */

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Report\Functional;

use Codeception\Scenario;
use Qameta\Allure\Codeception\Test\Report\FunctionalTester;

/** @var Scenario $scenario */
$I = new FunctionalTester($scenario);
$I->expect('some condition');
