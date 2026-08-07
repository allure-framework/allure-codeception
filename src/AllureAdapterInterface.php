<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception;

interface AllureAdapterInterface
{
    public function setActiveFixture(string $uuid, string $hookName): void;

    public function getActiveFixtureUuid(): ?string;

    public function getActiveHookName(): ?string;

    public function clearActiveFixture(): void;

    public function hasEmittedHookGlobalError(string $fixtureUuid): bool;

    public function markHookGlobalErrorEmitted(string $fixtureUuid): void;

    public function registerSuiteContainer(string $suiteName, string $containerUuid): void;

    public function getSuiteContainerId(string $suiteName): ?string;

    public function clearSuiteContainer(string $suiteName): void;

    public function markTestStarted(string $testUuid): void;

    public function wasTestStarted(string $testUuid): bool;

    public function clearTestStarted(string $testUuid): void;
}
