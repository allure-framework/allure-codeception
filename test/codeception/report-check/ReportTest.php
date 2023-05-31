<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit;

use Codeception\Test\Unit;
use Qameta\Allure\Codeception\Test\Report\Functional\NestedStepsCest;
use Qameta\Allure\Codeception\Test\Report\Unit\AnnotationTest;
use Qameta\Allure\Codeception\Test\Report\Unit\StepsTest;
use Remorhaz\JSON\Data\Value\EncodedJson\NodeValueFactory;
use Remorhaz\JSON\Data\Value\NodeValueInterface;
use Remorhaz\JSON\Path\Processor\Processor;
use Remorhaz\JSON\Path\Processor\ProcessorInterface;
use Remorhaz\JSON\Path\Query\QueryFactory;
use Remorhaz\JSON\Path\Query\QueryFactoryInterface;
use RuntimeException;

use function file_get_contents;
use function is_file;
use function pathinfo;
use function scandir;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

class ReportTest extends Unit
{
    /**
     * @var array<string, array<string, NodeValueInterface>>
     */
    private static array $testResults = [];

    private ?ProcessorInterface $jsonPathProcessor = null;

    private ?QueryFactoryInterface $jsonPathQueryFactory = null;

    public static function setUpBeforeClass(): void
    {
        $buildPath = __DIR__ . '/../../../build/allure-results';
        $files = scandir($buildPath);

        $jsonValueFactory = NodeValueFactory::create();
        $jsonPathProcessor = Processor::create();
        $jsonPathQueryFactory = QueryFactory::create();
        $testMethodsQuery = $jsonPathQueryFactory
            ->createQuery('$.labels[?(@.name=="testMethod")].value');
        $testClassesQuery = $jsonPathQueryFactory
            ->createQuery('$.labels[?(@.name=="testClass")].value');

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
                $data = $jsonValueFactory->createValue($fileContent);
                /** @var mixed $class */
                $class = $jsonPathProcessor
                    ->select($testClassesQuery, $data)
                    ->decode()[0] ?? null;
                /** @var mixed $method */
                $method = $jsonPathProcessor
                    ->select($testMethodsQuery, $data)
                    ->decode()[0] ?? null;
                if (!isset($class, $method)) {
                    throw new RuntimeException("Test not found in file $file");
                }
                self::assertIsString($class);
                self::assertIsString($method);
                self::$testResults[$class][$method] = $data;
            }
        }
    }

    /**
     * @param string $class
     * @param string $method
     * @param string $jsonPath
     * @param non-empty-string $expectedValue
     * @dataProvider providerSingleNodeValueStartsFromString
     */
    public function testSingleNodeValueStartsFromString(
        string $class,
        string $method,
        string $jsonPath,
        string $expectedValue
    ): void {
        /** @psalm-var mixed $nodes */
        $nodes = $this
            ->getJsonPathProcessor()
            ->select(
                $this->getJsonPathQueryFactory()->createQuery($jsonPath),
                self::$testResults[$class][$method]
                    ?? throw new RuntimeException("Result not found for $class::$method"),
            )
            ->decode();
        self::assertIsArray($nodes);
        self::assertCount(1, $nodes);
        $value = $nodes[0] ?? null;
        self::assertIsString($value);
        self::assertStringStartsWith($expectedValue, $value);
    }

    /**
     * @return iterable<string, array{string, string, string, non-empty-string}>
     */
    public static function providerSingleNodeValueStartsFromString(): iterable
    {
        return [
            'Error message in test case without steps' => [
                StepsTest::class,
                'testNoStepsError',
                '$.statusDetails.message',
                "Error\nException(0)",
            ],
        ];
    }

    /**
     * @dataProvider providerExistingNodeValue
     */
    public function testExistingNodeValue(
        string $class,
        string $method,
        string $jsonPath,
        array $expected
    ): void {
        $nodes = $this
            ->getJsonPathProcessor()
            ->select(
                $this->getJsonPathQueryFactory()->createQuery($jsonPath),
                self::$testResults[$class][$method]
                    ?? throw new RuntimeException("Result not found for $class::$method"),
            )
            ->decode();
        self::assertSame($expected, $nodes);
    }

    /**
     * @return iterable<string, array{string, string, string, list<mixed>}>
     */
    public static function providerExistingNodeValue(): iterable
    {
        return [
            'Test case title annotation' => [
                AnnotationTest::class,
                'testTitleAnnotation',
                '$.name',
                ['Test title'],
            ],
            'Test case severity annotation' => [
                AnnotationTest::class,
                'testSeverityAnnotation',
                '$.labels[?(@.name=="severity")].value',
                ['minor'],
            ],
            'Test case parameter annotation' => [
                AnnotationTest::class,
                'testParameterAnnotation',
                '$.parameters[?(@.name=="foo")].value',
                ['bar'],
            ],
            'Test case stories annotation' => [
                AnnotationTest::class,
                'testStoriesAnnotation',
                '$.labels[?(@.name=="story")].value',
                ['Story 2', 'Story 1'],
            ],
            'Test case features annotation' => [
                AnnotationTest::class,
                'testFeaturesAnnotation',
                '$.labels[?(@.name=="feature")].value',
                ['Feature 2', 'Feature 1'],
            ],
            'Successful test case without steps' => [
                StepsTest::class,
                'testNoStepsSuccess',
                '$.status',
                ['passed'],
            ],
            'Successful test case without steps: no steps' => [
                StepsTest::class,
                'testNoStepsSuccess',
                '$.steps[*]',
                [],
            ],
            'Error in test case without steps' => [
                StepsTest::class,
                'testNoStepsError',
                '$.status',
                ['broken'],
            ],
            'Failure message in test case without steps' => [
                StepsTest::class,
                'testNoStepsFailure',
                '$.statusDetails.message',
                ['Failure'],
            ],
            'Test case without steps skipped' => [
                StepsTest::class,
                'testNoStepsSkipped',
                '$.status',
                ['skipped'],
            ],
            'Skipped message in test case without steps' => [
                StepsTest::class,
                'testNoStepsSkipped',
                '$.statusDetails.message',
                ['Skipped'],
            ],
            'Successful test case with single step: status' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithTitle',
                '$.status',
                ['passed'],
            ],
            'Successful test case with single step: step status' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithTitle',
                '$.steps[*].status',
                ['passed'],
            ],
            'Successful test case with single step: step name' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithTitle',
                '$.steps[*].name',
                ['step 1 name'],
            ],
            'Successful test case with arguments in step: status' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithArguments',
                '$.status',
                ['passed'],
            ],
            'Successful test case with arguments in step: step status' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithArguments',
                '$.steps[*].status',
                ['passed'],
            ],
            'Successful test case with arguments in step: step name' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithArguments',
                '$.steps[*].name',
                ['step 1 name'],
            ],
            'Successful test case with arguments in step: step parameter' => [
                StepsTest::class,
                'testSingleSuccessfulStepWithArguments',
                '$.steps[*].parameters[?(@.name=="foo")].value',
                ['"bar"'],
            ],
            'Successful test case with two successful steps: status' => [
                StepsTest::class,
                'testTwoSuccessfulSteps',
                '$.status',
                ['passed'],
            ],
            'Successful test case with two successful steps: step status' => [
                StepsTest::class,
                'testTwoSuccessfulSteps',
                '$.steps[*].status',
                ['passed', 'passed'],
            ],
            'Successful test case with two successful steps: step name' => [
                StepsTest::class,
                'testTwoSuccessfulSteps',
                '$.steps[*].name',
                ['step 1 name', 'step 2 name'],
            ],
            'First step in test case with two steps fails: status' => [
                StepsTest::class,
                'testTwoStepsFirstFails',
                '$.status',
                ['failed'],
            ],
            'First step in test case with two steps fails: message' => [
                StepsTest::class,
                'testTwoStepsFirstFails',
                '$.statusDetails.message',
                ['Failure'],
            ],
            'First step in test case with two steps fails: step status' => [
                StepsTest::class,
                'testTwoStepsFirstFails',
                '$.steps[*].status',
                ['failed'],
            ],
            'First step in test case with two steps fails: step name' => [
                StepsTest::class,
                'testTwoStepsFirstFails',
                '$.steps[*].name',
                ['step 1 name'],
            ],
            'Second step in test case with two steps fails: status' => [
                StepsTest::class,
                'testTwoStepsSecondFails',
                '$.status',
                ['failed'],
            ],
            'Second step in test case with two steps fails: message' => [
                StepsTest::class,
                'testTwoStepsSecondFails',
                '$.statusDetails.message',
                ['Failure'],
            ],
            'Second step in test case with two steps fails: step status' => [
                StepsTest::class,
                'testTwoStepsSecondFails',
                '$.steps[*].status',
                ['passed', 'failed'],
            ],
            'Second step in test case with two steps fails: step name' => [
                StepsTest::class,
                'testTwoStepsSecondFails',
                '$.steps[*].name',
                ['step 1 name', 'step 2 name'],
            ],
            'Nested steps: root names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                '$.steps[*].name',
                ['Step 1'],
            ],
            'Nested steps: level 1 names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                '$.steps[?(@.name=="Step 1")].steps[*].name',
                ['i expect condition 1', 'Step 1.1', 'Step 1.2'],
            ],
            'Nested steps: level 1.1 names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                '$.steps..steps[?(@.name=="Step 1.1")].steps[*].name',
                ['i expect condition 1.1', 'Step 1.1.1'],
            ],
            'Nested steps: level 1.1.1 names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                '$.steps..steps[?(@.name=="Step 1.1.1")].steps[*].name',
                ['i expect condition 1.1.1'],
            ],
            'Nested steps: level 1.2 names' => [
                NestedStepsCest::class,
                'makeNestedSteps',
                '$.steps..steps[?(@.name=="Step 1.2")].steps[*].name',
                ['i expect condition 1.2'],
            ],
        ];
    }

    private function getJsonPathProcessor(): ProcessorInterface
    {
        return $this->jsonPathProcessor ??= Processor::create();
    }

    private function getJsonPathQueryFactory(): QueryFactoryInterface
    {
        return $this->jsonPathQueryFactory ??= QueryFactory::create();
    }
}
