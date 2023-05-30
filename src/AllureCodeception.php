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
use Qameta\Allure\Setup\DefaultStatusDetector;
use Qameta\Allure\Setup\LinkTemplate;
use Qameta\Allure\Setup\LinkTemplateInterface;

use function class_exists;
use function is_a;
use function is_array;
use function is_callable;
use function is_string;
use function trim;

use const DIRECTORY_SEPARATOR;

final class AllureCodeception extends Extension
{
    private const SETUP_HOOK_PARAMETER = 'setupHook';
    private const OUTPUT_DIRECTORY_PARAMETER = 'outputDirectory';
    private const LINK_TEMPLATES_PARAMETER = 'linkTemplates';

    private const DEFAULT_RESULTS_DIRECTORY = 'allure-results';

    protected static array $events = [
        Events::MODULE_INIT => 'moduleInit',
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
    public function moduleInit(): void
    {
        QametaAllure::reset();
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
     * @psalm-suppress MissingDependency
     */
    public function suiteBefore(SuiteEvent $suiteEvent): void
    {
        /** @psalm-suppress InternalMethod */
        $suiteName = $suiteEvent->getSuite()?->getName();
        if (!isset($suiteName)) {
            return;
        }

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
        $this
            ->getTestLifecycle()
            ->switchToTest($failEvent->getTest())
            ->updateTestFailure(
                $failEvent->getFail(),
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
            ->updateTestFailure(
                $failEvent->getFail(),
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

    private function getTestLifecycle(): TestLifecycleInterface
    {
        return $this->testLifecycle ??= new TestLifecycle(
            Allure::getLifecycle(),
            Allure::getConfig()->getResultFactory(),
            Allure::getConfig()->getStatusDetector(),
            $this->getThreadDetector(),
            Allure::getConfig()->getLinkTemplates(),
            $_ENV,
        );
    }
}
