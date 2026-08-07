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
use PHPUnit\Framework\AssertionFailedError;
use Qameta\Allure\Allure;
use Qameta\Allure\Allure as QametaAllure;
use Qameta\Allure\Codeception\Internal\DefaultThreadDetector;
use Qameta\Allure\Codeception\Internal\SuiteInfo;
use Qameta\Allure\Codeception\Internal\TestLifecycle;
use Qameta\Allure\Codeception\Internal\TestLifecycleInterface;
use Qameta\Allure\Codeception\Setup\ThreadDetectorInterface;
use Qameta\Allure\Model\LinkType;
use Qameta\Allure\Model\Status;
use Qameta\Allure\Model\StatusDetails;
use Qameta\Allure\Setup\LinkTemplate;
use Qameta\Allure\Setup\LinkTemplateInterface;
use Throwable;

use function array_reverse;
use function class_exists;
use function is_a;
use function is_array;
use function is_callable;
use function is_string;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Allure Codeception extension.
 *
 * Codeception has no per-hook Called/Failed events. Module hook phases are reported
 * as Allure fixtures by wrapping SUITE_BEFORE / SUITE_AFTER / TEST_BEFORE / TEST_AFTER
 * (invoking Module hooks from this extension and stopPropagation so the Module
 * subscriber does not double-run — required so `_beforeSuite` failures still emit
 * globals: SuiteManager dispatches SUITE_BEFORE outside its try/finally).
 *
 * Fixtures:
 * - `_beforeSuite` / `_afterSuite` → suite container (beforeClass folded into before)
 * - `_before` / `_after` → per-test container
 *
 * Cannot split Module vs @beforeClass with the extension alone.
 *
 * Not wrapped (no Codeception events): Cest `_before`/`_after`/`@before`/`@after`,
 * Unit setUp/tearDown, step hooks, Module `_failed`.
 *
 * Lifecycle: testStart creates/schedules the test but defers startTest until `_before`
 * succeeds (Prepared-after-hooks alignment with allure-phpunit).
 */
final class AllureCodeception extends Extension
{
    private const SETUP_HOOK_PARAMETER = 'setupHook';
    private const OUTPUT_DIRECTORY_PARAMETER = 'outputDirectory';
    private const LINK_TEMPLATES_PARAMETER = 'linkTemplates';

    private const DEFAULT_RESULTS_DIRECTORY = 'allure-results';

    protected static array $events = [
        Events::MODULE_INIT => 'moduleInit',
        Events::SUITE_BEFORE => [
            ['suiteBeforeHookPhase', 100],
        ],
        Events::SUITE_AFTER => [
            ['suiteAfterHookStart', 200],
            ['suiteAfterModuleWrap', 1],
        ],
        Events::TEST_START => 'testStart',
        Events::TEST_BEFORE => [
            ['testBeforeHookPhase', 100],
        ],
        Events::TEST_AFTER => [
            ['testAfterHookPhase', 100],
        ],
        Events::TEST_FAIL => 'testFail',
        Events::TEST_ERROR => 'testError',
        Events::TEST_INCOMPLETE => 'testIncomplete',
        Events::TEST_SKIPPED => 'testSkipped',
        Events::TEST_SUCCESS => 'testSuccess',
        Events::TEST_END => 'testEnd',
        Events::STEP_BEFORE => 'stepBefore',
        Events::STEP_AFTER => 'stepAfter',
    ];

    private ?ThreadDetectorInterface $threadDetector = null;

    private ?TestLifecycleInterface $testLifecycle = null;

    /**
     * {@inheritDoc}
     *
     * @throws ConfigurationException
     * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
     */
    #[\Override]
    public function _initialize(): void
    {
        parent::_initialize();
        $this->reconfigure();
    }

    /**
     * @throws ConfigurationException
     */
    public function moduleInit(): void
    {
        $this->reconfigure();
    }

    private function reconfigure(): void
    {
        QametaAllure::reset();
        AllureAdapter::reset();
        $this->testLifecycle = null;
        $this->threadDetector = null;
        QametaAllure::getLifecycleConfigurator()
            ->setOutputDirectory($this->getOutputDirectory());
        foreach ($this->getLinkTemplates() as $linkType => $linkTemplate) {
            QametaAllure::getLifecycleConfigurator()->addLinkTemplate($linkType, $linkTemplate);
        }
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
     * @psalm-suppress MoreSpecificReturnType
     * @return iterable<LinkType, LinkTemplate>
     */
    private function getLinkTemplates(): iterable
    {
        /**
         * @var mixed $templatesConfig
         * @psalm-var array $this->config
         */
        $templatesConfig = $this->config[self::LINK_TEMPLATES_PARAMETER] ?? [];
        if (!is_array($templatesConfig)) {
            $templatesConfig = [];
        }
        foreach ($templatesConfig as $linkTypeName => $linkConfig) {
            if (!is_string($linkConfig) || !is_string($linkTypeName)) {
                continue;
            }
            yield LinkType::fromOptionalString($linkTypeName) =>
                class_exists($linkConfig) && is_a($linkConfig, LinkTemplateInterface::class, true)
                    ? new $linkConfig()
                    : new LinkTemplate($linkConfig);
        }
    }

    /**
     * Wraps `_beforeSuite` (+ beforeClass) because SUITE_BEFORE throws abort SuiteManager
     * before its try/finally — SUITE_AFTER never runs, so orphan flush cannot help.
     *
     * @psalm-suppress MissingDependency
     */
    public function suiteBeforeHookPhase(SuiteEvent $suiteEvent): void
    {
        /** @psalm-suppress InternalMethod */
        $suiteName = $suiteEvent->getSuite()?->getName();
        if (!isset($suiteName)) {
            return;
        }

        $lifecycle = $this
            ->getTestLifecycle()
            ->switchToSuite(new SuiteInfo($suiteName))
            ->ensureSuiteContainer()
            ->startBeforeHookFixture('_beforeSuite');

        try {
            $this->runBeforeClassMethods($suiteEvent);
            foreach ($this->getModulesList() as $module) {
                $module->_beforeSuite($suiteEvent->getSettings());
            }
            $lifecycle->completeHookFixtureSuccess();
        } catch (Throwable $e) {
            $lifecycle
                ->completeHookFixtureFailure(
                    $this->statusForHookThrowable($e),
                    $e->getMessage(),
                    $e->getTraceAsString(),
                )
                ->writeSuiteContainer()
                ->resetSuite();
            throw $e;
        } finally {
            $suiteEvent->stopPropagation();
        }
    }

    public function suiteAfterHookStart(): void
    {
        // Module::_after may throw and skip TEST_END.
        $this
            ->getTestLifecycle()
            ->flushActiveHookFixtureFailure(Status::broken())
            ->finalizePendingTest()
            ->ensureSuiteContainer()
            ->startAfterHookFixture('_afterSuite');
    }

    /**
     * Runs Module `_afterSuite` inside the active fixture and stops propagation so
     * Module subscriber does not double-invoke. afterClass (prio 100) already ran.
     */
    public function suiteAfterModuleWrap(SuiteEvent $suiteEvent): void
    {
        $lifecycle = $this->getTestLifecycle();
        try {
            foreach (array_reverse($this->getModulesList()) as $module) {
                $module->_afterSuite();
            }
            $lifecycle
                ->completeHookFixtureSuccess()
                ->writeSuiteContainer()
                ->resetSuite();
        } catch (Throwable $e) {
            $lifecycle
                ->completeHookFixtureFailure(
                    $this->statusForHookThrowable($e),
                    $e->getMessage(),
                    $e->getTraceAsString(),
                )
                ->writeSuiteContainer()
                ->resetSuite();
            throw $e;
        } finally {
            $suiteEvent->stopPropagation();
        }
    }

    /**
     * Wraps Module `_after` so failures still emit fixture + global (low-prio stop
     * would be skipped when Module throws; TEST_END would also be skipped).
     *
     * @psalm-suppress MissingDependency
     */
    public function testAfterHookPhase(TestEvent $testEvent): void
    {
        $lifecycle = $this
            ->getTestLifecycle()
            ->switchToTest($testEvent->getTest())
            ->startAfterHookFixture('_after');

        try {
            foreach (array_reverse($this->getModulesList()) as $module) {
                $module->_after($testEvent->getTest());
                $module->_resetConfig();
            }
            $lifecycle->completeHookFixtureSuccess();
        } catch (Throwable $e) {
            $lifecycle->completeHookFixtureFailure(
                $this->statusForHookThrowable($e),
                $e->getMessage(),
                $e->getTraceAsString(),
            );
            throw $e;
        } finally {
            $testEvent->stopPropagation();
        }
    }

    /**
     * @return list<\Codeception\Module>
     */
    private function getModulesList(): array
    {
        $modules = [];
        foreach ($this->getCurrentModuleNames() as $name) {
            $modules[] = $this->getModule($name);
        }

        return $modules;
    }

    /**
     * Mirrors Codeception\Subscriber\BeforeAfterTest::beforeClass so it stays inside
     * the `_beforeSuite` fixture when we stopPropagation on SUITE_BEFORE.
     */
    private function runBeforeClassMethods(SuiteEvent $suiteEvent): void
    {
        $suite = $suiteEvent->getSuite();
        if ($suite === null) {
            return;
        }

        foreach ($suite->getTests() as $test) {
            $methods = $test->getMetadata()->getBeforeClassMethods();
            $target = $test;
            if ($test instanceof \Codeception\Test\TestCaseWrapper) {
                $target = $test->getTestCase();
            }
            foreach ($methods as $method) {
                if (is_callable([$target, $method])) {
                    $target->{$method}();
                }
            }
        }
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
            ->updateTest();
        // startTest deferred until TEST_BEFORE succeeds (Prepared-after-hooks alignment).
    }

    /**
     * Wraps Module `_before` using Extension module list. Required because
     * suiteBeforeHookPhase stopPropagation skips Module::beforeSuite which would
     * otherwise populate Module subscriber's module list for TEST_BEFORE.
     *
     * @psalm-suppress MissingDependency
     */
    public function testBeforeHookPhase(TestEvent $testEvent): void
    {
        $lifecycle = $this
            ->getTestLifecycle()
            ->switchToTest($testEvent->getTest())
            ->startBeforeHookFixture('_before');

        try {
            foreach ($this->getModulesList() as $module) {
                $module->_before($testEvent->getTest());
            }
            $lifecycle
                ->completeHookFixtureSuccess()
                ->startTest();
        } catch (Throwable $e) {
            $lifecycle->completeHookFixtureFailure(
                $this->statusForHookThrowable($e),
                $e->getMessage(),
                $e->getTraceAsString(),
            );
            throw $e;
        } finally {
            $testEvent->stopPropagation();
        }
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
        $error = $failEvent->getFail();
        $status = $this->statusForHookThrowable($error);
        $this
            ->getTestLifecycle()
            ->switchToTest($failEvent->getTest())
            ->completeOrphanHookFailure(
                $status,
                $error->getMessage(),
                $error->getTraceAsString(),
            )
            ->updateTestFailure(
                $error,
                Status::broken(),
            );
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testFail(FailEvent $failEvent): void
    {
        $error = $failEvent->getFail();
        $this
            ->getTestLifecycle()
            ->switchToTest($failEvent->getTest())
            ->completeOrphanHookFailure(
                Status::failed(),
                $error->getMessage(),
                $error->getTraceAsString(),
            )
            ->updateTestFailure(
                $error,
                Status::failed(),
                new StatusDetails(message: $error->getMessage(), trace: $error->getTraceAsString()),
            );
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function testIncomplete(FailEvent $failEvent): void
    {
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
        $this
            ->getTestLifecycle()
            ->switchToTest($stepEvent->getTest())
            ->startStep($stepEvent->getStep())
            ->updateStep();
    }

    /**
     * @psalm-suppress MissingDependency
     */
    public function stepAfter(StepEvent $stepEvent): void
    {
        $this
            ->getTestLifecycle()
            ->switchToTest($stepEvent->getTest())
            ->switchToStep($stepEvent->getStep())
            ->updateStepResult()
            ->stopStep();
    }

    private function statusForHookThrowable(Throwable $error): Status
    {
        return $error instanceof AssertionFailedError
            ? Status::failed()
            : Status::broken();
    }

    private function getTestLifecycle(): TestLifecycleInterface
    {
        return $this->testLifecycle ??= new TestLifecycle(
            rootDir: $this->getRootDir(),
            lifecycle: Allure::getLifecycle(),
            resultFactory: Allure::getConfig()->getResultFactory(),
            statusDetector: Allure::getConfig()->getStatusDetector(),
            threadDetector: $this->getThreadDetector(),
            linkTemplates: Allure::getConfig()->getLinkTemplates(),
            env: $_ENV,
            adapter: AllureAdapter::getInstance(),
        );
    }
}
