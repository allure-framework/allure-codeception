<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Step;

final class StepStartInfo
{
    public function __construct(
        private Step $originalStep,
        private string $uuid,
    ) {
    }

    public function getOriginalStep(): Step
    {
        return $this->originalStep;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
