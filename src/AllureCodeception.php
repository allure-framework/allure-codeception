<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception;

use Codeception\Configuration;
use Codeception\Extension;
use Codeception\Event\FailEvent;
use Codeception\Event\StepEvent;
use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Exception\ConfigurationException;
use Codeception\Step;
use Qameta\Allure\Allure;
use Qameta\Allure\Allure as QametaAllure;
use Qameta\Allure\Codeception\Internal\DefaultThreadDetector;
use Qameta\Allure\Codeception\Internal\SuiteInfo;
use Qameta\Allure\Codeception\Internal\TestLifecycle;
use Qameta\Allure\Codeception\Internal\TestLifecycleInterface;
use Qameta\Allure\Codeception\Setup\ThreadDetectorInterface;
use Qameta\Allure\Model\Status;
use Qameta\Allure\Model\StatusDetails;
use Qameta\Allure\Setup\DefaultStatusDetector;
use Throwable;

use function class_exists;
use function is_callable;
use function is_string;
use function trim;

use const DIRECTORY_SEPARATOR;

final class AllureCodeception extends Extension
{
    private const SETUP_HOOK_PARAMETER = 'setupHook';
    private const OUTPUT_DIRECTORY_PARAMETER = 'outputDirectory';

    private const DEFAULT_RESULTS_DIRECTORY = 'allure-results';

    protected static array $events = [
        Events::SUITE_BEFORE => 'suiteBefore',
        Events::SUITE_AFTER => 'suiteAfter',
        Events::TEST_START => 'testStart',
        Events::TEST_FAIL => 'testFail',
        Events::TEST_ERROR => 'testError',
        Events::TEST_INCOMPLETE => 'testIncomplete',
        Events::TEST_SKIPPED => 'testSkipped',
        Events::TEST_SUCCESS => 'testSuccess',
        Events::TEST_END => 'testEnd',
        Events::STEP_BEFORE => 'stepBefore',
        Events::STEP_AFTER => 'stepAfter'
    ];

    private ?ThreadDetectorInterface $threadDetector = null;

    private ?TestLifecycleInterface $testLifecycle = null;

    /**
     * {@inheritDoc}
     *
     * @throws ConfigurationException
     * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    public function _initialize(): void
    {
        // phpcs:enable PSR2.Methods.MethodDeclaration.Underscore
        parent::_initialize();
        QametaAllure::reset();
        QametaAllure::getLifecycleConfigurator()
            ->setStatusDetector(new StatusDetector(new DefaultStatusDetector()))
            ->setOutputDirectory($this->getOutputDirectory());
        $this->callSetupHook();
    }

    private function callSetupHook(): void
    {
        /**
         * @var mixed $hookClass
         * @psalm-var array $this->config
         */
        $hookClass = $this->config[self::SETUP_HOOK_PARAMETER] ?? '';
        /** @psalm-suppress MixedMethodCall */
        $hook = is_string($hookClass) && class_exists($hookClass)
            ? new $hookClass()
            : null;

        if (is_callable($hook)) {
            $hook();
        }
    }

    /**
     * @throws ConfigurationException
     */
    private function getOutputDirectory(): string
    {
        /**
         * @var mixed $outputCfg
         * @psalm-var array $this->config
         */
        $outputCfg = $this->config[self::OUTPUT_DIRECTORY_PARAMETER] ?? null;
        $outputLocal = is_string($outputCfg)
            ? trim($outputCfg, '\\/')
            : null;

        return Configuration::outputDir() . ($outputLocal ?? self::DEFAULT_RESULTS_DIRECTORY) . DIRECTORY_SEPARATOR;
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function suiteBefore(SuiteEvent $suiteEvent): void
    {
        /** @psalm-suppress InternalMethod */
        $suiteName = $suiteEvent->getSuite()->getName();
        $this
            ->getTestLifecycle()
            ->switchToSuite(new SuiteInfo($suiteName));
    }

    public function suiteAfter(): void
    {
        $this
            ->getTestLifecycle()
            ->resetSuite();
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testStart(TestEvent $testEvent): void
    {
        $test = $testEvent->getTest();
        $this
            ->getTestLifecycle()
            ->switchToTest($test)
            ->create()
            ->updateTest()
            ->startTest();
    }

    private function getThreadDetector(): ThreadDetectorInterface
    {
        return $this->threadDetector ??= new DefaultThreadDetector();
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testError(FailEvent $failEvent): void
    {
        /** @var Throwable $error */
        $error = $failEvent->getFail();
        $this
            ->getTestLifecycle()
            ->switchToTest($failEvent->getTest())
            ->updateTestFailure($error);
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testFail(FailEvent $failEvent): void
    {
        /** @var Throwable $error */
        $error = $failEvent->getFail();
        $this
            ->getTestLifecycle()
            ->switchToTest($failEvent->getTest())
            ->updateTestFailure($error, Status::failed());
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testIncomplete(FailEvent $failEvent): void
    {
        /** @var Throwable $error */
        $error = $failEvent->getFail();
        $this
            ->getTestLifecycle()
            ->switchToTest($failEvent->getTest())
            ->updateTestFailure(
                $error,
                Status::broken(),
                new StatusDetails(message: $error->getMessage(), trace: $error->getTraceAsString()),
            );
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testSkipped(FailEvent $failEvent): void
    {
        /** @var Throwable $error */
        $error = $failEvent->getFail();
        $this
            ->getTestLifecycle()
            ->switchToTest($failEvent->getTest())
            ->updateTestFailure(
                $error,
                Status::skipped(),
                new StatusDetails(message: $error->getMessage(), trace: $error->getTraceAsString()),
            );
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testSuccess(TestEvent $testEvent): void
    {
        $this
            ->getTestLifecycle()
            ->switchToTest($testEvent->getTest())
            ->updateTestSuccess();
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testEnd(TestEvent $testEvent): void
    {
        $this
            ->getTestLifecycle()
            ->switchToTest($testEvent->getTest())
            ->updateTestResult()
            ->attachReports()
            ->stopTest();
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function stepBefore(StepEvent $stepEvent): void
    {
        /** @psalm-var Step $step */
        $step = $stepEvent->getStep();
        $this
            ->getTestLifecycle()
            ->switchToTest($stepEvent->getTest())
            ->startStep($step)
            ->updateStep();
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function stepAfter(StepEvent $stepEvent): void
    {
        /** @psalm-var Step $step */
        $step = $stepEvent->getStep();
        $this
            ->getTestLifecycle()
            ->switchToTest($stepEvent->getTest())
            ->switchToStep($step)
            ->updateStepResult()
            ->stopStep();
    }

    private function getTestLifecycle(): TestLifecycleInterface
    {
        return $this->testLifecycle ??= new TestLifecycle(
            Allure::getLifecycle(),
            Allure::getConfig()->getResultFactory(),
            Allure::getConfig()->getStatusDetector(),
            $this->getThreadDetector(),
            Allure::getConfig()->getLinkTemplates(),
        );
    }
}
