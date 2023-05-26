<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Step;
use Codeception\Test\Cept;
use Codeception\Test\Cest;
use Codeception\Test\Gherkin;
use Codeception\Test\TestCaseWrapper;
use Codeception\TestInterface;
use Qameta\Allure\AllureLifecycleInterface;
use Qameta\Allure\Codeception\Setup\ThreadDetectorInterface;
use Qameta\Allure\Io\DataSourceFactory;
use Qameta\Allure\Model\EnvProvider;
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

    /**
     * @psalm-var WeakMap<Step, StepStartInfo>
     */
    private WeakMap $stepStarts;

    public function __construct(
        private AllureLifecycleInterface $lifecycle,
        private ResultFactoryInterface $resultFactory,
        private StatusDetectorInterface $statusDetector,
        private ThreadDetectorInterface $threadDetector,
        private LinkTemplateCollectionInterface $linkTemplates,
        private array $env,
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

    public function switchToSuite(SuiteInfo $suiteInfo): self
    {
        $this->currentSuite = $suiteInfo;

        return $this;
    }

    public function resetSuite(): self
    {
        $this->currentSuite = null;

        return $this;
    }

    public function switchToTest(object $test): self
    {
        $thread = $this->threadDetector->getThread();
        $this->lifecycle->switchThread($thread);

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
            $test instanceof Gherkin => new GherkinInfoBuilder($test),
            $test instanceof Cept => new CeptInfoBuilder($test),
            $test instanceof TestCaseWrapper => new UnitInfoBuilder($test),
            default => new UnknownInfoBuilder($test),
        };
    }

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

        return $this;
    }

    public function updateTest(): self
    {
        $provider = new ModelProviderChain(
            new EnvProvider($this->env),
            ...SuiteProvider::createForChain($this->getCurrentSuite(), $this->linkTemplates),
            ...TestInfoProvider::createForChain($this->getCurrentTest()),
            ...$this->createModelProvidersForTest($this->getCurrentTest()->getOriginalTest()),
        );
        $this->lifecycle->updateTest(
            fn (TestResult $t) => $t
                ->setName($provider->getDisplayName())
                ->setFullName($provider->getFullName())
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

    public function startTest(): self
    {
        $this->lifecycle->startTest($this->getCurrentTestStart()->getTestUuid());

        return $this;
    }

    public function stopTest(): self
    {
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

        $this->currentTest = null;
        $this->currentTestStart = null;

        return $this;
    }

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

    public function updateTestSuccess(): self
    {
        $this->lifecycle->updateTest(
            fn (TestResult $t) => $t->setStatus(Status::passed()),
        );

        return $this;
    }

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

    public function switchToStep(Step $step): self
    {
        $this->currentStepStart =
            $this->stepStarts[$step] ?? throw new RuntimeException("Step start info not found");

        return $this;
    }

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
}
