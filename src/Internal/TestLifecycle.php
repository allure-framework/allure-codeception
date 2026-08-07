<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Step;
use Codeception\Test\Cept;
use Codeception\Test\Cest;
use Codeception\Test\Gherkin;
use Codeception\Test\TestCaseWrapper;
use Codeception\TestInterface;
use Qameta\Allure\Allure;
use Qameta\Allure\AllureLifecycleInterface;
use Qameta\Allure\Codeception\AllureAdapterInterface;
use Qameta\Allure\Codeception\Setup\ThreadDetectorInterface;
use Qameta\Allure\Io\DataSourceFactory;
use Qameta\Allure\Model\EnvProvider;
use Qameta\Allure\Model\FixtureResult;
use Qameta\Allure\Model\ModelProviderChain;
use Qameta\Allure\Model\Parameter;
use Qameta\Allure\Model\ResultFactoryInterface;
use Qameta\Allure\Model\Status;
use Qameta\Allure\Model\StatusDetails;
use Qameta\Allure\Model\StepResult;
use Qameta\Allure\Model\TestResult;
use Qameta\Allure\Setup\LinkTemplateCollectionInterface;
use Qameta\Allure\Setup\StatusDetectorInterface;
use RuntimeException;
use Throwable;
use WeakMap;

use function array_filter;
use function file_exists;
use function is_file;
use function is_string;

final class TestLifecycle implements TestLifecycleInterface
{
    private ?SuiteInfo $currentSuite = null;

    private ?TestInfo $currentTest = null;

    private ?TestStartInfo $currentTestStart = null;

    private ?StepStartInfo $currentStepStart = null;

    private bool $suiteHookContext = false;

    /**
     * @psalm-var WeakMap<Step, StepStartInfo>
     */
    private WeakMap $stepStarts;

    public function __construct(
        private string $rootDir,
        private AllureLifecycleInterface $lifecycle,
        private ResultFactoryInterface $resultFactory,
        private StatusDetectorInterface $statusDetector,
        private ThreadDetectorInterface $threadDetector,
        private LinkTemplateCollectionInterface $linkTemplates,
        private array $env,
        private AllureAdapterInterface $adapter,
    ) {
        /** @psalm-var WeakMap<Step, StepStartInfo> $this->stepStarts */
        $this->stepStarts = new WeakMap();
    }

    public function getCurrentSuite(): SuiteInfo
    {
        return $this->currentSuite ?? throw new RuntimeException("Current suite not found");
    }

    public function getCurrentTest(): TestInfo
    {
        return $this->currentTest ?? throw new RuntimeException("Current test not found");
    }

    public function getCurrentTestStart(): TestStartInfo
    {
        return $this->currentTestStart ?? throw new RuntimeException("Current test start not found");
    }

    public function getCurrentStepStart(): StepStartInfo
    {
        return $this->currentStepStart ?? throw new RuntimeException("Current step start not found");
    }

    #[\Override]
    public function switchToSuite(SuiteInfo $suiteInfo): self
    {
        $this->currentSuite = $suiteInfo;

        return $this;
    }

    #[\Override]
    public function resetSuite(): self
    {
        $this->currentSuite = null;
        $this->suiteHookContext = false;

        return $this;
    }

    #[\Override]
    public function switchToTest(object $test): self
    {
        $thread = $this->threadDetector->getThread();
        $this->lifecycle->switchThread($thread);

        $this->suiteHookContext = false;
        $this->currentTest = $this
            ->getTestInfoBuilder($test)
            ->build(
                $this->threadDetector->getHost(),
                $thread,
            );

        return $this;
    }

    private function getTestInfoBuilder(object $test): TestInfoBuilderInterface
    {
        return match (true) {
            $test instanceof Cest => new CestInfoBuilder($test),
            $test instanceof Gherkin => new GherkinInfoBuilder($this->rootDir, $test),
            $test instanceof Cept => new CeptInfoBuilder($this->rootDir, $test),
            $test instanceof TestCaseWrapper => new UnitInfoBuilder($test),
            default => new UnknownInfoBuilder($test),
        };
    }

    #[\Override]
    public function create(): self
    {
        $containerResult = $this->resultFactory->createContainer();
        $this->lifecycle->startContainer($containerResult);

        $testResult = $this->resultFactory->createTest();
        $this->lifecycle->scheduleTest($testResult, $containerResult->getUuid());

        $this->currentTestStart = new TestStartInfo(
            containerUuid: $containerResult->getUuid(),
            testUuid: $testResult->getUuid(),
        );
        $this->adapter->clearTestStarted($testResult->getUuid());

        return $this;
    }

    #[\Override]
    public function updateTest(): self
    {
        $test = $this->getCurrentTest();
        $provider = new ModelProviderChain(
            new EnvProvider($this->env),
            ...SuiteProvider::createForChain($this->getCurrentSuite(), $this->linkTemplates),
            ...TestInfoProvider::createForChain($test),
            ...$this->createModelProvidersForTest($test->getOriginalTest()),
        );
        $this->lifecycle->updateTest(
            fn (TestResult $t) => $t
                ->setName($provider->getDisplayName())
                ->setFullName($provider->getFullName())
                ->setTitlePath(...$test->getTitlePath())
                ->setDescription($provider->getDescription())
                ->setDescriptionHtml($provider->getDescriptionHtml())
                ->addLinks(...$provider->getLinks())
                ->addLabels(...$provider->getLabels())
                ->addParameters(...$provider->getParameters()),
            $this->getCurrentTestStart()->getTestUuid(),
        );

        return $this;
    }

    private function createModelProvidersForTest(mixed $test): array
    {
        return match (true) {
            $test instanceof Cest => CestProvider::createForChain($test, $this->linkTemplates),
            $test instanceof Gherkin => GherkinProvider::createForChain($test),
            $test instanceof Cept => CeptProvider::createForChain($test, $this->linkTemplates),
            $test instanceof TestCaseWrapper => UnitProvider::createForChain($test, $this->linkTemplates),
            default => [],
        };
    }

    #[\Override]
    public function startTest(): self
    {
        $testUuid = $this->getCurrentTestStart()->getTestUuid();
        $this->lifecycle->startTest($testUuid);
        $this->adapter->markTestStarted($testUuid);

        return $this;
    }

    #[\Override]
    public function stopTest(): self
    {
        $this->completeHookFixtureSuccess();

        $testUuid = $this->getCurrentTestStart()->getTestUuid();
        $this
            ->lifecycle
            ->stopTest($testUuid);
        $this->lifecycle->writeTest($testUuid);

        $containerUuid = $this->getCurrentTestStart()->getContainerUuid();
        $this
            ->lifecycle
            ->stopContainer($containerUuid);
        $this->lifecycle->writeContainer($containerUuid);

        $this->adapter->clearTestStarted($testUuid);
        $this->currentTest = null;
        $this->currentTestStart = null;

        return $this;
    }

    #[\Override]
    public function updateTestFailure(
        Throwable $error,
        ?Status $status = null,
        ?StatusDetails $statusDetails = null,
    ): self {
        $this->lifecycle->updateTest(
            fn (TestResult $t) => $t
                ->setStatus($status ?? $this->statusDetector->getStatus($error))
                ->setStatusDetails($statusDetails ?? $this->statusDetector->getStatusDetails($error)),
        );

        return $this;
    }

    #[\Override]
    public function updateTestSuccess(): self
    {
        $this->lifecycle->updateTest(
            fn (TestResult $t) => $t->setStatus(Status::passed()),
        );

        return $this;
    }

    #[\Override]
    public function attachReports(): self
    {
        $originalTest = $this->getCurrentTest()->getOriginalTest();
        if ($originalTest instanceof TestInterface) {
            $artifacts = $originalTest->getMetadata()->getReports();
            /**
             * @psalm-var mixed $artifact
             */
            foreach ($artifacts as $name => $artifact) {
                $attachment = $this
                    ->resultFactory
                    ->createAttachment()
                    ->setName((string) $name);
                if (!is_string($artifact)) {
                    continue;
                }
                $dataSource = @file_exists($artifact) && is_file($artifact)
                    ? DataSourceFactory::fromFile($artifact)
                    : DataSourceFactory::fromString($artifact);
                $this
                    ->lifecycle
                    ->addAttachment($attachment, $dataSource);
            }
        }

        return $this;
    }

    #[\Override]
    public function updateTestResult(): self
    {
        $this->lifecycle->updateTest(
            fn (TestResult $t) => $t
                ->setTestCaseId($testCaseId = $this->buildTestCaseId($this->getCurrentTest(), ...$t->getParameters()))
                ->setHistoryId($this->buildHistoryId($testCaseId, $this->getCurrentTest(), ...$t->getParameters())),
            $this->getCurrentTestStart()->getTestUuid(),
        );

        return $this;
    }

    private function buildTestCaseId(TestInfo $testInfo, Parameter ...$parameters): string
    {
        $parameterNames = implode(
            '::',
            array_map(
                fn (Parameter $parameter): string => $parameter->getName(),
                array_filter(
                    $parameters,
                    fn (Parameter $parameter): bool => !$parameter->getExcluded(),
                ),
            ),
        );

        return md5("{$testInfo->getSignature()}::$parameterNames");
    }

    private function buildHistoryId(string $testCaseId, TestInfo $testInfo, Parameter ...$parameters): string
    {
        $parameterNames = implode(
            '::',
            array_map(
                fn (Parameter $parameter): string => $parameter->getValue() ?? '',
                array_filter(
                    $parameters,
                    fn (Parameter $parameter): bool => !$parameter->getExcluded(),
                ),
            ),
        );

        return md5("$testCaseId::{$testInfo->getSignature()}::$parameterNames");
    }

    #[\Override]
    public function startStep(Step $step): self
    {
        $stepResult = $this->resultFactory->createStep();
        $this->lifecycle->startStep($stepResult);

        $stepStart = new StepStartInfo(
            $step,
            $stepResult->getUuid(),
        );
        $this->stepStarts[$step] = $stepStart;
        $this->currentStepStart = $stepStart;

        return $this;
    }

    #[\Override]
    public function switchToStep(Step $step): self
    {
        $this->currentStepStart =
            $this->stepStarts[$step] ?? throw new RuntimeException("Step start info not found");

        return $this;
    }

    #[\Override]
    public function stopStep(): self
    {
        $stepStart = $this->getCurrentStepStart();
        $this->lifecycle->stopStep($stepStart->getUuid());
        /**
         * @psalm-var Step $step
         * @psalm-var StepStartInfo $storedStart
         */
        foreach ($this->stepStarts as $step => $storedStart) {
            if ($storedStart === $stepStart) {
                unset($this->stepStarts[$step]);
            }
        }
        $this->currentStepStart = null;

        return $this;
    }

    #[\Override]
    public function updateStep(): self
    {
        $stepStart = $this->getCurrentStepStart();
        $step = $stepStart->getOriginalStep();

        $params = [];
        /** @psalm-var mixed $value */
        foreach ($step->getArguments() as $name => $value) {
            $params[] = new Parameter(
                is_int($name) ? "#$name" : $name,
                ArgumentAsString::get($value),
            );
        }
        /** @var mixed $humanizedAction */
        $humanizedAction = $step->getHumanizedActionWithoutArguments();
        $this->lifecycle->updateStep(
            fn (StepResult $s) => $s
                ->setName(is_string($humanizedAction) ? $humanizedAction : null)
                ->setParameters(...$params),
            $stepStart->getUuid(),
        );

        return $this;
    }

    #[\Override]
    public function updateStepResult(): self
    {
        $this->lifecycle->updateStep(
            fn (StepResult $s) => $s
                ->setStatus(
                    $this->getCurrentStepStart()->getOriginalStep()->hasFailed()
                        ? Status::failed()
                        : Status::passed(),
                ),
        );

        return $this;
    }

    #[\Override]
    public function ensureSuiteContainer(): self
    {
        $suite = $this->getCurrentSuite();
        $this->suiteHookContext = true;

        if ($this->adapter->getSuiteContainerId($suite->getName()) !== null) {
            return $this;
        }

        $containerResult = $this->resultFactory->createContainer();
        $this->lifecycle->startContainer($containerResult);
        $this->adapter->registerSuiteContainer($suite->getName(), $containerResult->getUuid());

        return $this;
    }

    #[\Override]
    public function writeSuiteContainer(): self
    {
        $suite = $this->currentSuite;
        if ($suite === null) {
            return $this;
        }

        $containerId = $this->adapter->getSuiteContainerId($suite->getName());
        if ($containerId === null) {
            return $this;
        }

        $this->completeHookFixtureSuccess();
        $this->lifecycle->stopContainer($containerId);
        $this->lifecycle->writeContainer($containerId);
        $this->adapter->clearSuiteContainer($suite->getName());
        $this->suiteHookContext = false;

        return $this;
    }

    #[\Override]
    public function startBeforeHookFixture(string $hookName): self
    {
        return $this->startHookFixture($hookName, before: true);
    }

    #[\Override]
    public function startAfterHookFixture(string $hookName): self
    {
        return $this->startHookFixture($hookName, before: false);
    }

    #[\Override]
    public function completeHookFixtureSuccess(): self
    {
        $uuid = $this->adapter->getActiveFixtureUuid();
        if ($uuid === null) {
            return $this;
        }

        $this->lifecycle->updateFixture(
            static fn (FixtureResult $fixture) => $fixture->setStatus(Status::passed()),
            $uuid,
        );
        $this->lifecycle->stopFixture($uuid);
        $this->adapter->clearActiveFixture();

        return $this;
    }

    #[\Override]
    public function completeHookFixtureFailure(
        Status $status,
        ?string $message = null,
        ?string $trace = null,
    ): self {
        $uuid = $this->adapter->getActiveFixtureUuid();
        $hookName = $this->adapter->getActiveHookName() ?? 'hook';
        if ($uuid === null) {
            return $this;
        }

        $prefixed = HookFailureMessage::format($hookName, $message);

        $this->lifecycle->updateFixture(
            static function (FixtureResult $fixture) use ($status, $prefixed, $trace): void {
                $fixture
                    ->setStatus($status)
                    ->setStatusDetails(
                        (new StatusDetails())
                            ->setMessage($prefixed)
                            ->setTrace($trace),
                    );
            },
            $uuid,
        );
        $this->lifecycle->stopFixture($uuid);

        if (!$this->adapter->hasEmittedHookGlobalError($uuid)) {
            Allure::globalError($prefixed, $trace);
            $this->adapter->markHookGlobalErrorEmitted($uuid);
        }

        $this->adapter->clearActiveFixture();

        return $this;
    }

    #[\Override]
    public function completeOrphanHookFailure(
        Status $status,
        ?string $message = null,
        ?string $trace = null,
    ): self {
        if ($this->currentTestStart === null) {
            return $this;
        }

        if ($this->adapter->getActiveFixtureUuid() === null) {
            return $this;
        }

        if ($this->adapter->wasTestStarted($this->getCurrentTestStart()->getTestUuid())) {
            return $this;
        }

        $this->completeHookFixtureFailure($status, $message, $trace);

        return $this;
    }

    #[\Override]
    public function flushActiveHookFixtureFailure(
        Status $status,
        ?string $message = null,
        ?string $trace = null,
    ): self {
        if ($this->adapter->getActiveFixtureUuid() === null) {
            return $this;
        }

        $this->completeHookFixtureFailure($status, $message, $trace);

        return $this;
    }

    #[\Override]
    public function finalizePendingTest(): self
    {
        if ($this->currentTestStart === null) {
            return $this;
        }

        $this->updateTestResult();
        $this->stopTest();

        return $this;
    }

    private function startHookFixture(string $hookName, bool $before): self
    {
        $this->completeHookFixtureSuccess();

        $fixture = $this
            ->resultFactory
            ->createFixture()
            ->setName($hookName);

        $containerId = $this->resolveContainerId();
        if ($before) {
            $this->lifecycle->startBeforeFixture($fixture, $containerId);
        } else {
            $this->lifecycle->startAfterFixture($fixture, $containerId);
        }

        $this->adapter->setActiveFixture($fixture->getUuid(), $hookName);

        return $this;
    }

    private function resolveContainerId(): string
    {
        if ($this->suiteHookContext) {
            $suite = $this->getCurrentSuite();

            return $this->adapter->getSuiteContainerId($suite->getName())
                ?? throw new RuntimeException("Suite container is not set for {$suite->getName()}");
        }

        return $this->getCurrentTestStart()->getContainerUuid();
    }
}
