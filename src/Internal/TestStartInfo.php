<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

final class TestStartInfo
{
    public function __construct(
        private string $containerUuid,
        private string $testUuid,
    ) {
    }

    public function getContainerUuid(): string
    {
        return $this->containerUuid;
    }

    public function getTestUuid(): string
    {
        return $this->testUuid;
    }
}
