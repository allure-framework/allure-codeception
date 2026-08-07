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

    public function ensureSuiteContainer(): TestLifecycleInterface;

    public function writeSuiteContainer(): TestLifecycleInterface;

    public function startBeforeHookFixture(string $hookName): TestLifecycleInterface;

    public function startAfterHookFixture(string $hookName): TestLifecycleInterface;

    public function completeHookFixtureSuccess(): TestLifecycleInterface;

    public function completeHookFixtureFailure(
        Status $status,
        ?string $message = null,
        ?string $trace = null,
    ): TestLifecycleInterface;

    /**
     * Completes an active before-hook fixture when Module throws and the low-priority
     * stop listener does not run; failure surfaces as TEST_ERROR / TEST_FAIL.
     */
    public function completeOrphanHookFailure(
        Status $status,
        ?string $message = null,
        ?string $trace = null,
    ): TestLifecycleInterface;

    /**
     * Completes a still-active after-hook fixture (e.g. Module::_after threw and
     * TEST_END was skipped) before suite teardown.
     */
    public function flushActiveHookFixtureFailure(
        Status $status,
        ?string $message = null,
        ?string $trace = null,
    ): TestLifecycleInterface;

    /**
     * Writes the current test/container when TEST_END was skipped (e.g. `_after` threw).
     */
    public function finalizePendingTest(): TestLifecycleInterface;
}
