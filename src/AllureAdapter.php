<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception;

final class AllureAdapter implements AllureAdapterInterface
{
    private static ?AllureAdapterInterface $instance = null;

    /**
     * @var array<string, string>
     */
    private array $suiteContainers = [];

    /**
     * @var array<string, true>
     */
    private array $emittedHookGlobals = [];

    /**
     * @var array<string, true>
     */
    private array $startedTests = [];

    private ?string $activeFixtureUuid = null;

    private ?string $activeHookName = null;

    private function __construct()
    {
    }

    public static function getInstance(): AllureAdapterInterface
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(AllureAdapterInterface $instance): void
    {
        self::$instance = $instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    #[\Override]
    public function setActiveFixture(string $uuid, string $hookName): void
    {
        $this->activeFixtureUuid = $uuid;
        $this->activeHookName = $hookName;
    }

    #[\Override]
    public function getActiveFixtureUuid(): ?string
    {
        return $this->activeFixtureUuid;
    }

    #[\Override]
    public function getActiveHookName(): ?string
    {
        return $this->activeHookName;
    }

    #[\Override]
    public function clearActiveFixture(): void
    {
        $this->activeFixtureUuid = null;
        $this->activeHookName = null;
    }

    #[\Override]
    public function hasEmittedHookGlobalError(string $fixtureUuid): bool
    {
        return isset($this->emittedHookGlobals[$fixtureUuid]);
    }

    #[\Override]
    public function markHookGlobalErrorEmitted(string $fixtureUuid): void
    {
        $this->emittedHookGlobals[$fixtureUuid] = true;
    }

    #[\Override]
    public function registerSuiteContainer(string $suiteName, string $containerUuid): void
    {
        $this->suiteContainers[$suiteName] = $containerUuid;
    }

    #[\Override]
    public function getSuiteContainerId(string $suiteName): ?string
    {
        return $this->suiteContainers[$suiteName] ?? null;
    }

    #[\Override]
    public function clearSuiteContainer(string $suiteName): void
    {
        unset($this->suiteContainers[$suiteName]);
    }

    #[\Override]
    public function markTestStarted(string $testUuid): void
    {
        $this->startedTests[$testUuid] = true;
    }

    #[\Override]
    public function wasTestStarted(string $testUuid): bool
    {
        return isset($this->startedTests[$testUuid]);
    }

    #[\Override]
    public function clearTestStarted(string $testUuid): void
    {
        unset($this->startedTests[$testUuid]);
    }
}
