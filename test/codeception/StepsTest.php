<?php

declare(strict_types=1);

namespace Yandex\Allure\Codeception;

use Codeception\Lib\ModuleContainer;
use Codeception\Scenario;
use Codeception\Step\Assertion;
use Codeception\Step\Comment;
use Codeception\Step\Meta;
use Codeception\Step\TryTo;
use Codeception\Test\Unit;
use Exception;
use PHPUnit\Framework\Assert;

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
        self::fail('Failure');
    }

    public function testNoStepsSkipped(): void
    {
        self::markTestSkipped('Skipped');
    }

    public function testSingleSuccessfulStepWithTitle(): void
    {
        $this->expectNotToPerformAssertions();
        $scenario = new Scenario($this);
        $scenario->runStep(new Comment('Step 1 name'));
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

    private function createFailingStep(string $name, string $failure): \Codeception\Step
    {
        return new class ($failure, $name) extends Meta {

            private $failure;

            public function __construct(string $failure, $action, array $arguments = [])
            {
                parent::__construct($action, $arguments);
                $this->failure = $failure;
            }

            public function run(ModuleContainer $container = null)
            {
                $this->setFailed(true);
                Assert::fail($this->failure);
            }
        };
    }
}
