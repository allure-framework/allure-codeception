<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit;

use Codeception\Test\Unit;
use Qameta\Allure\Codeception\Test\Report\Functional\NestedStepsCest;
use Qameta\Allure\Codeception\Test\Report\Unit\AnnotationTest;
use Qameta\Allure\Codeception\Test\Report\Unit\StepsTest;
use RuntimeException;

use function file_get_contents;
use function is_file;
use function json_decode;
use function pathinfo;
use function scandir;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use const PATHINFO_EXTENSION;

class ReportTest extends Unit
{
    /**
    * @var array<string, array<string, object>>
     */
    private static array $testResults = [];

    public static function setUpBeforeClass(): void
    {
        $buildPath = __DIR__ . '/../../../build/allure-results';
        $files = scandir($buildPath);
        if ($files === false || empty($files)) {
            throw new RuntimeException("No test results found. Run 'composer test-report-generate' first");
        }

        foreach ($files as $fileName) {
            $file = $buildPath . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($file)) {
                continue;
            }
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if ('json' == $extension) {
                $fileName = pathinfo($file, PATHINFO_FILENAME);
                if (!str_ends_with($fileName, '-result')) {
                    continue;
                }
                $fileContent = file_get_contents($file);
                if ($fileContent === false) {
                    throw new RuntimeException("Can't read " . $file);
                }

                $testResult = json_decode($fileContent, flags: JSON_THROW_ON_ERROR);
                self::assertIsObject($testResult);

                $class = self::findLabel($testResult, "testClass");
                $method = self::findLabel($testResult, "testMethod");

                self::$testResults[$class][$method] = $testResult;
            }
        }
    }

    /**
     * @param string $class
     * @param string $method
        * @param callable(object): mixed $selector
     * @param non-empty-string $expectedValue
     * @dataProvider providerSingleNodeValueStartsFromString
     */
    public function testSingleNodeValueStartsFromString(
        string $class,
        string $method,
        callable $selector,
        string $expectedValue
    ): void {
        $testResult = self::$testResults[$class][$method]
            ?? throw new RuntimeException("Result not found for $class::$method");

        $actualResult = $selector($testResult);

        self::assertIsString($actualResult);
        self::assertStringStartsWith($expectedValue, $actualResult);
    }

    /**
     * @return iterable<string, array{string, string, callable(object): mixed, non-empty-string}>
     */
    public static function providerSingleNodeValueStartsFromString(): iterable
    {
        return [
            'Error message in test case without steps' => [
                StepsTest::class,
                'testNoStepsError',
                fn (object $tr): mixed => self::property(self::objectProperty($tr, 'statusDetails'), 'message'),
                "Error\nException(0)",
            ],
        ];
    }

    /**
     * @dataProvider providerExistingNodeValue
     * @param callable(object): mixed $selector
     */
    public function testExistingNodeValue(
        string $class,
        string $method,
        callable $selector,
        mixed $expected
    ): void {
        $testResult = self::$testResults[$class][$method]
            ?? throw new RuntimeException("Result not found for $class::$method");

        /** @psalm-var mixed $actualResult */
        $actualResult = $selector($testResult);

        self::assertSame($expected, $actualResult);
    }

    /**
     * @return iterable<string, array{string, string, callable(object): mixed, mixed}>
     */
    public static function providerExistingNodeValue(): iterable
    {
        return [
            'Test case title annotation' => [
                AnnotationTest::class,
                'testTitleAnnotation',
                fn (object $tr): mixed => $tr->name,
                'Test title',
            ],
            'Test case severity annotation' => [
                AnnotationTest::class,
                'testSeverityAnnotation',
                fn (object $tr): mixed => self::findLabel($tr, "severity"),
                'minor',
            ],
            'Test case parameter annotation' => [
                AnnotationTest::class,
                'testParameterAnnotation',
                fn (object $tr): mixed => self::findParameter($tr, "foo")->value,
                'bar',
            ],
            'Test case stories annotation' => [
                AnnotationTest::class,
                'testStoriesAnnotation',
                fn (object $tr): mixed => self::findLabels($tr, "story"),
                ['Story 2', 'Story 1'],
            ],
            'Test case features annotation' => [
                AnnotationTest::class,
                'testFeaturesAnnotation',
                fn (object $tr): mixed => self::findLabels($tr, "feature"),
                ['Feature 2', 'Feature 1'],
            ],
            'Successful test case without steps' => [
                StepsTest::class,
                'testNoStepsSuccess',
                fn (object $tr): mixed => $tr->status,
                'passed',
            ],
            'Successful test case without steps: no steps' => [
                StepsTest::class,
                'testNoStepsSuccess',
                fn (object $tr): mixed => self::objectListProperty($tr, 'steps'),
                [],
            ],
            'Error in test case without steps' => [
                StepsTest::class,
                'testNoStepsError',
                fn (object $tr): mixed => $tr->status,
                'broken',
            ],
            'Failure message in test case without steps' => [
                StepsTest::class,
                'testNoStepsFailure',
                fn (object $tr): mixed => self::property(self::objectProperty($tr, 'statusDetails'), 'message'),
                'Failure',
            ],
            'Test case without steps skipped' => [
                StepsTest::class,
                'testNoStepsSkipped',
                fn (object $tr): mixed => $tr->status,
                'skipped',
            ],
            'Skipped message in test case without steps' => [
                StepsTest::class,
                'testNoStepsSkipped',
                fn (object $tr): mixed => self::property(self::objectProperty($tr, 'statusDetails'), 'message'),
                'Skipped',
            ],
            'Successful test case with single step: status' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithTitle',
                fn (object $tr): mixed => $tr->status,
                'passed',
            ],
            'Successful test case with single step: step status' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithTitle',
                fn (object $tr): mixed => self::singleStep($tr)->status,
                'passed',
            ],
            'Successful test case with single step: step name' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithTitle',
                fn (object $tr): mixed => self::singleStep($tr)->name,
                'step 1 name',
            ],
            'Successful test case with arguments in step: status' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithArguments',
                fn (object $tr): mixed => $tr->status,
                'passed',
            ],
            'Successful test case with arguments in step: step status' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithArguments',
                fn (object $tr): mixed => self::singleStep($tr)->status,
                'passed',
            ],
            'Successful test case with arguments in step: step name' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithArguments',
                fn (object $tr): mixed => self::singleStep($tr)->name,
                'step 1 name',
            ],
            'Successful test case with arguments in step: step parameter' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithArguments',
                fn (object $tr): mixed => self::findParameter(self::singleStep($tr), "foo")->value,
                '"bar"',
            ],
            'Successful test case with two successful steps: status' => [
                StepsTest::class,
                'testTwoSuccessfulSteps',
                fn (object $tr): mixed => $tr->status,
                'passed',
            ],
            'Successful test case with two successful steps: step status' => [
                StepsTest::class,
                'testTwoSuccessfulSteps',
                fn (object $tr): mixed => array_map(
                    fn (object $s): mixed => self::property($s, 'status'),
                    self::objectListProperty($tr, 'steps'),
                ),
                ['passed', 'passed'],
            ],
            'Successful test case with two successful steps: step name' => [
                StepsTest::class,
                'testTwoSuccessfulSteps',
                fn (object $tr): mixed => array_map(
                    fn (object $s): mixed => self::property($s, 'name'),
                    self::objectListProperty($tr, 'steps'),
                ),
                ['step 1 name', 'step 2 name'],
            ],
            'First step in test case with two steps fails: status' => [
                StepsTest::class,
                'testTwoStepsFirstFails',
                fn (object $tr): mixed => $tr->status,
                'failed',
            ],
            'First step in test case with two steps fails: message' => [
                StepsTest::class,
                'testTwoStepsFirstFails',
                fn (object $tr): mixed => self::property(self::objectProperty($tr, 'statusDetails'), 'message'),
                'Failure',
            ],
            'First step in test case with two steps fails: step status' => [
                StepsTest::class,
                'testTwoStepsFirstFails',
                fn (object $tr): mixed => self::singleStep($tr)->status,
                'failed',
            ],
            'First step in test case with two steps fails: step name' => [
                StepsTest::class,
                'testTwoStepsFirstFails',
                fn (object $tr): mixed => self::singleStep($tr)->name,
                'step 1 name',
            ],
            'Second step in test case with two steps fails: status' => [
                StepsTest::class,
                'testTwoStepsSecondFails',
                fn (object $tr): mixed => $tr->status,
                'failed',
            ],
            'Second step in test case with two steps fails: message' => [
                StepsTest::class,
                'testTwoStepsSecondFails',
                fn (object $tr): mixed => self::property(self::objectProperty($tr, 'statusDetails'), 'message'),
                'Failure',
            ],
            'Second step in test case with two steps fails: step status' => [
                StepsTest::class,
                'testTwoStepsSecondFails',
                fn (object $tr): mixed => array_map(
                    fn (object $s): mixed => self::property($s, 'status'),
                    self::objectListProperty($tr, 'steps'),
                ),
                ['passed', 'failed'],
            ],
            'Second step in test case with two steps fails: step name' => [
                StepsTest::class,
                'testTwoStepsSecondFails',
                fn (object $tr): mixed => array_map(
                    fn (object $s): mixed => self::property($s, 'name'),
                    self::objectListProperty($tr, 'steps'),
                ),
                ['step 1 name', 'step 2 name'],
            ],
            'Nested steps: root names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                fn (object $tr): mixed => self::singleStep($tr)->name,
                'Step 1',
            ],
            'Nested steps: step 1 substep names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                fn (object $tr): mixed => array_map(
                    fn (object $s): mixed => self::property($s, 'name'),
                    self::objectListProperty(self::singleStep($tr), 'steps'),
                ),
                ['i expect condition 1', 'Step 1.1', 'Step 1.2'],
            ],
            'Nested steps: step 1.1 substep names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                fn (object $tr): mixed => array_map(
                    fn (object $s): mixed => self::property($s, 'name'),
                    self::objectListProperty(self::findStep(self::singleStep($tr), "Step 1.1"), 'steps'),
                ),
                ['i expect condition 1.1', 'Step 1.1.1'],
            ],
            'Nested steps: level 1.1.1 names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                fn (object $tr): mixed => self::singleStep(
                    self::findStep(
                        self::findStep(
                            self::singleStep($tr),
                            "Step 1.1"
                        ),
                        "Step 1.1.1",
                    ),
                )->name,
                'i expect condition 1.1.1',
            ],
            'Nested steps: level 1.2 names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                fn (object $tr): mixed => array_map(
                    fn (object $s): mixed => self::property($s, 'name'),
                    self::objectListProperty(self::findStep(self::singleStep($tr), "Step 1.2"), 'steps'),
                ),
                ['i expect condition 1.2'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function findLabels(object $testResult, string $name): array
    {
        $labels = self::objectListProperty($testResult, 'labels');

        $values = array_values(
            array_map(
                fn (object $label): mixed => $label->value,
                array_filter(
                    $labels,
                    fn (object $label): bool => $label->name === $name,
                ),
            )
        );

        $result = [];
        foreach ($values as $value) {
            self::assertIsString($value);
            $result[] = $value;
        }

        return $result;
    }

    private static function findLabel(object $testResult, string $name): string
    {
        $labels = self::findLabels($testResult, $name);

        self::assertCount(1, $labels);

        return $labels[0];
    }

    private static function findStep(object $parent, string $name): object
    {
        $steps = self::objectListProperty($parent, 'steps');

        $steps = array_values(
            array_filter(
                $steps,
                fn (object $step): bool => $step->name === $name,
            ),
        );

        self::assertCount(1, $steps);

        return $steps[0];
    }

    private static function singleStep(object $parent): object
    {
        $steps = self::objectListProperty($parent, 'steps');

        self::assertCount(1, $steps);

        return $steps[0];
    }

    private static function findParameter(object $testResult, string $name): object
    {
        $parameters = self::objectListProperty($testResult, 'parameters');

        $parameters = array_values(array_filter(
            $parameters,
            fn (object $parameter): bool => $parameter->name === $name,
        ));

        self::assertCount(1, $parameters);

        return $parameters[0];
    }

    private static function property(object $value, string $property): mixed
    {
        return $value->{$property};
    }

    private static function objectProperty(object $value, string $property): object
    {
        $result = self::property($value, $property);
        self::assertIsObject($result);

        return $result;
    }

    /**
     * @return list<object>
     */
    private static function objectListProperty(object $value, string $property): array
    {
        $result = self::property($value, $property);
        self::assertIsArray($result);

        /** @var list<object> */
        return array_values($result);
    }
}
