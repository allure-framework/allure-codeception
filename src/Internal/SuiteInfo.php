<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use function class_exists;

/**
 * @internal
 */
final class SuiteInfo
{
    public function __construct(
        private string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return class-string|null
     */
    public function getClass(): ?string
    {
        return class_exists($this->name, false)
            ? $this->name
            : null;
    }
}
