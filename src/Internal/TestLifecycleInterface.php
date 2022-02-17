<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Step;
use Qameta\Allure\Model\Status;
use Qameta\Allure\Model\StatusDetails;
use Throwable;

interface TestLifecycleInterface
{
    public function switchToSuite(SuiteInfo $suiteInfo): TestLifecycleInterface;

    public function resetSuite(): TestLifecycleInterface;

    public function switchToTest(object $test): TestLifecycleInterface;

    public function create(): TestLifecycleInterface;

    public function updateTest(): TestLifecycleInterface;

    public function startTest(): TestLifecycleInterface;

    public function stopTest(): TestLifecycleInterface;

    public function updateTestFailure(
        Throwable $error,
        ?Status $status = null,
        ?StatusDetails $statusDetails = null,
    ): TestLifecycleInterface;

    public function updateTestSuccess(): TestLifecycleInterface;

    public function attachReports(): TestLifecycleInterface;

    public function updateTestResult(): TestLifecycleInterface;

    public function startStep(Step $step): TestLifecycleInterface;

    public function switchToStep(Step $step): TestLifecycleInterface;

    public function stopStep(): TestLifecycleInterface;

    public function updateStep(): TestLifecycleInterface;

    public function updateStepResult(): TestLifecycleInterface;
}
