<?php

declare(strict_types=1);

namespace Yandex\Allure\Codeception;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function is_file;
use function pathinfo;
use function scandir;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

class ReportTest extends TestCase
{

    /**
     * @var string
     */
    private $buildPath;

    /**
     * @var DOMXPath[]
     */
    private $sources = [];

    public function setUp(): void
    {
        $this->buildPath = __DIR__ . '/../../build/allure-results';
        $files = scandir($this->buildPath);

        foreach ($files as $fileName) {
            $file = $this->buildPath . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($file)) {
                continue;
            }
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if ('xml' == $extension) {
                $dom = new DOMDocument();
                $dom->load($file);

                $path = new DOMXPath($dom);
                $name = $path->query('/alr:test-suite/name')->item(0)->textContent;
                if (isset($this->sources[$name])) {
                    throw new RuntimeException("Duplicate test suite: {$name}");
                }
                $this->sources[$name] = $path;
            }
        }
    }

    /**
     * @param string $class
     * @param string $xpath
     * @param string $expectedValue
     * @dataProvider providerSingleTextNode
     */
    public function testSingleTextNode(string $class, string $xpath, string $expectedValue): void
    {
        self::assertArrayHasKey($class, $this->sources);
        $actualValue = $this
            ->sources[$class]
            ->query($xpath)
            ->item(0)
            ->textContent;
        self::assertSame($expectedValue, $actualValue);
    }

    public function providerSingleTextNode(): iterable
    {
        return [
            'Test case title annotation' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testTitleAnnotation',
                    '/title'
                ),
                'Test title',
            ],
            'Test case severity annotation' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testSeverityAnnotation',
                    '/labels/label[@name="severity" and @value="minor"]'
                ),
                '',
            ],
            'Test case parameter annotation' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testParameterAnnotation',
                    '/parameters/parameter[@name="foo" and @value="bar" and @kind="argument"]'
                ),
                '',
            ],
            'Test case stories annotation: first story' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testStoriesAnnotation',
                    '/labels/label[@name="story" and @value="Story 1"]'
                ),
                '',
            ],
            'Test case stories annotation: second story' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testStoriesAnnotation',
                    '/labels/label[@name="story" and @value="Story 2"]'
                ),
                '',
            ],
            'Test case features annotation: first feature' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testFeaturesAnnotation',
                    '/labels/label[@name="feature" and @value="Feature 1"]'
                ),
                '',
            ],
            'Test case features annotation: second feature' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testFeaturesAnnotation',
                    '/labels/label[@name="feature" and @value="Feature 2"]'
                ),
                '',
            ],
            'Successful test case without steps' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testNoStepsSuccess',
                '[@status="passed"]/name'
                ),
                'testNoStepsSuccess',
            ],
            'Error in test case without steps' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testNoStepsError',
                '[@status="broken"]/failure/message'
                ),
                'Error',
            ],
            'Failure in test case without steps' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testNoStepsFailure',
                '[@status="failed"]/failure/message'
                ),
                'Failure',
            ],
            'Test case without steps skipped' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testNoStepsSkipped',
                '[@status="canceled"]/failure/message'
                ),
                'Skipped',
            ],
            'Successful test case with single step: name' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testSingleSuccessfulStepWithTitle',
                '[@status="passed"]/steps/step[1][@status="passed"]/name'
                ),
                'step 1 name ', // Codeception processes action internally
            ],
            'Successful test case with two successful steps: step 2 name' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testTwoSuccessfulSteps',
                    '[@status="passed"]/steps/step[2][@status="passed"]/name'
                ),
                'step 2 name ', // Codeception processes action internally
            ],
            'First step in test case with two steps fails: failure' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testTwoStepsFirstFails',
                    '[@status="failed"]/failure/message'
                ),
                'Failure',
            ],
            'First step in test case with two steps fails: step 1 name' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testTwoStepsFirstFails',
                    '[@status="failed"]/steps/step[1][@status="failed"]/name'
                ),
                'step 1 name ', // Codeception processes action internally
            ],
            'Second step in test case with two steps fails: failure' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testTwoStepsSecondFails',
                    '[@status="failed"]/failure/message'
                ),
                'Failure',
            ],
            'Second step in test case with two steps fails: step 1 name' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testTwoStepsSecondFails',
                    '[@status="failed"]/steps/step[1][@status="passed"]/name'
                ),
                'step 1 name ', // Codeception processes action internally
            ],
            'Second step in test case with two steps fails: step 2 name' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testTwoStepsSecondFails',
                    '[@status="failed"]/steps/step[2][@status="failed"]/name'
                ),
                'step 2 name ', // Codeception processes action internally
            ],
        ];
    }

    /**
     * @param string $class
     * @param string $xpath
     * @dataProvider providerNodeNotExists
     */
    public function testNodeNotExists(string $class, string $xpath): void
    {
        $testNode = $this
            ->sources[$class]
            ->query($xpath)
            ->item(0);
        self::assertNull($testNode);
    }

    public function providerNodeNotExists(): iterable
    {
        return [
            'Successful test case without steps: no steps' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testNoStepsSuccess',
                    '/steps'
                )
            ],
            'First step fails in test case with two steps: no second step' => [
                'Yandex\Allure\Codeception.unit',
                $this->buildTestXPath(
                    'testTwoStepsFirstFails',
                    '/steps/step[2]'
                )
            ],
        ];
    }

    private function buildTestXPath(string $testName, string $tail): string
    {
        return sprintf('/alr:test-suite/test-cases/test-case[name="%s"]%s', $testName, $tail);
    }
}
