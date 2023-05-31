<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Report\Unit;

use Codeception\Lib\ModuleContainer;
use Codeception\Scenario;
use Codeception\Step;
use Codeception\Step\Comment;
use Codeception\Step\Meta;
use Codeception\Test\Unit;
use Exception;

class StepsTest extends Unit
{
    public function testNoStepsSuccess(): void
    {
        $this->expectNotToPerformAssertions();
    }

    /**
     * @throws Exception
     */
    public function testNoStepsError(): void
    {
        throw new Exception('Error');
    }

    public function testNoStepsFailure(): void
    {
        /** @psalm-suppress UndefinedClass */
        self::fail('Failure');
    }

    public function testNoStepsSkipped(): void
    {
        /** @psalm-suppress UndefinedClass */
        self::markTestSkipped('Skipped');
    }

    public function testSingleSuccessfulStepWithTitle(): void
    {
        $this->expectNotToPerformAssertions();
        $scenario = new Scenario($this);
        $scenario->runStep(new Comment('Step 1 name'));
    }

    public function testSingleSuccessfulStepWithArguments(): void
    {
        $this->expectNotToPerformAssertions();
        $scenario = new Scenario($this);
        $scenario->runStep(new Comment('Step 1 name', ['foo' => 'bar']));
    }

    public function testTwoSuccessfulSteps(): void
    {
        $this->expectNotToPerformAssertions();

        $scenario = new Scenario($this);
        $scenario->runStep(new Comment('Step 1 name'));
        $scenario->runStep(new Comment('Step 2 name'));
    }

    public function testTwoStepsFirstFails(): void
    {
        $this->expectNotToPerformAssertions();

        $scenario = new Scenario($this);
        $scenario->runStep($this->createFailingStep('Step 1 name', 'Failure'));
        $scenario->runStep(new Comment('Step 2 name'));
    }

    public function testTwoStepsSecondFails(): void
    {
        $this->expectNotToPerformAssertions();

        $scenario = new Scenario($this);
        $scenario->runStep(new Comment('Step 1 name'));
        $scenario->runStep($this->createFailingStep('Step 2 name', 'Failure'));
    }

    private function createFailingStep(string $name, string $failure): Step
    {
        return new class ($failure, $name) extends Meta {
            private string $failure;

            public function __construct(string $failure, string $action, array $arguments = [])
            {
                parent::__construct($action, $arguments);
                $this->failure = $failure;
            }

            public function run(ModuleContainer $container = null): void
            {
                $this->setFailed(true);
                Unit::fail($this->failure);
            }
        };
    }
}
