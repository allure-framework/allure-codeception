<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit\Internal;

use Codeception\Test\Unit;
use Qameta\Allure\Allure;
use Qameta\Allure\Codeception\AllureAdapter;
use Qameta\Allure\Codeception\Internal\DefaultThreadDetector;
use Qameta\Allure\Codeception\Internal\SuiteInfo;
use Qameta\Allure\Codeception\Internal\TestLifecycle;
use Qameta\Allure\Model\Status;

use function file_get_contents;
use function glob;
use function is_array;
use function json_decode;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;

/**
 * Unit coverage for fixture success/failure + global error emission.
 *
 * Not covered here (no Codeception events for these): Cest `_before` / `_after` /
 * `@before` / `@after` (run inside Cest::test()), Unit setUp/tearDown (inside
 * PHPUnit case), step hooks, and Module `_failed`.
 */
final class TestLifecycleHookFixtureTest extends Unit
{
    private string $outputDirectory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Allure::reset();
        AllureAdapter::reset();
        $this->outputDirectory = sys_get_temp_dir() . '/allure-codeception-hook-' . uniqid('', true);
        mkdir($this->outputDirectory);
        Allure::getLifecycleConfigurator()->setOutputDirectory($this->outputDirectory);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Allure::reset();
        AllureAdapter::reset();
        parent::tearDown();
    }

    public function testCompleteHookFixtureFailure_EmitsPrefixedGlobalAndStopsFixture(): void
    {
        $lifecycle = $this->createLifecycle();
        $lifecycle
            ->switchToSuite(new SuiteInfo('unit'))
            ->switchToTest($this)
            ->create()
            ->startBeforeHookFixture('_before');
        $lifecycle->completeHookFixtureFailure(Status::broken(), 'boom', 'trace-line');

        $globals = $this->readGlobalErrors();
        self::assertCount(1, $globals);
        self::assertSame('_before failed: boom', $globals[0]['message'] ?? null);
        self::assertSame('trace-line', $globals[0]['trace'] ?? null);
    }

    public function testCompleteHookFixtureFailure_BlankMessage_UsesFallback(): void
    {
        $lifecycle = $this->createLifecycle();
        $lifecycle
            ->switchToSuite(new SuiteInfo('unit'))
            ->switchToTest($this)
            ->create()
            ->startBeforeHookFixture('_before');
        $lifecycle->completeHookFixtureFailure(Status::failed(), '   ', null);

        $globals = $this->readGlobalErrors();
        self::assertCount(1, $globals);
        self::assertSame('_before failed', $globals[0]['message'] ?? null);
    }

    public function testCompleteHookFixtureFailure_CalledTwice_DoesNotDuplicateGlobal(): void
    {
        $lifecycle = $this->createLifecycle();
        $lifecycle
            ->switchToSuite(new SuiteInfo('unit'))
            ->switchToTest($this)
            ->create()
            ->startBeforeHookFixture('_before');

        $adapter = AllureAdapter::getInstance();
        $uuid = $adapter->getActiveFixtureUuid();
        self::assertNotNull($uuid);

        $lifecycle->completeHookFixtureFailure(Status::broken(), 'once', 't');
        $adapter->setActiveFixture($uuid, '_before');
        $lifecycle->completeHookFixtureFailure(Status::broken(), 'twice', 't');

        $globals = $this->readGlobalErrors();
        self::assertCount(1, $globals);
        self::assertSame('_before failed: once', $globals[0]['message'] ?? null);
    }

    public function testCompleteHookFixtureSuccess_MarksFixturePassed(): void
    {
        $lifecycle = $this->createLifecycle();
        $lifecycle
            ->switchToSuite(new SuiteInfo('unit'))
            ->switchToTest($this)
            ->create()
            ->startBeforeHookFixture('_before')
            ->completeHookFixtureSuccess()
            ->startTest()
            ->stopTest();

        $fixtures = $this->readFixturesNamed('_before');
        self::assertNotEmpty($fixtures);
        self::assertSame('passed', $fixtures[0]['status'] ?? null);
    }

    private function createLifecycle(): TestLifecycle
    {
        return new TestLifecycle(
            rootDir: getcwd() ?: '/',
            lifecycle: Allure::getLifecycle(),
            resultFactory: Allure::getConfig()->getResultFactory(),
            statusDetector: Allure::getConfig()->getStatusDetector(),
            threadDetector: new DefaultThreadDetector(),
            linkTemplates: Allure::getConfig()->getLinkTemplates(),
            env: [],
            adapter: AllureAdapter::getInstance(),
        );
    }

    /**
     * @return list<array{message?: string, trace?: string}>
     */
    private function readGlobalErrors(): array
    {
        $files = glob($this->outputDirectory . '/*-globals.json');
        if (!is_array($files)) {
            return [];
        }

        $errors = [];
        foreach ($files as $file) {
            /** @var array{errors?: list<array{message?: string, trace?: string}>} $data */
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            foreach ($data['errors'] ?? [] as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return list<array{name?: string, status?: string}>
     */
    private function readFixturesNamed(string $hookName): array
    {
        $fixtures = [];
        $files = glob($this->outputDirectory . '/*-container.json');
        if (!is_array($files)) {
            return [];
        }

        foreach ($files as $file) {
            /**
             * @var array{
             *     befores?: list<array{name?: string, status?: string}>,
             *     afters?: list<array{name?: string, status?: string}>
             * } $data
             */
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            foreach ([...($data['befores'] ?? []), ...($data['afters'] ?? [])] as $fixture) {
                if (($fixture['name'] ?? null) === $hookName) {
                    $fixtures[] = $fixture;
                }
            }
        }

        return $fixtures;
    }
}
