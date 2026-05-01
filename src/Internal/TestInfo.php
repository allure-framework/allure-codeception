<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

final class TestInfo
{
    /**
     * @param list<string> $titlePath
     */
    public function __construct(
        private object $originalTest,
        private string $signature,
        private ?string $class = null,
        private ?string $method = null,
        private array $titlePath = [],
        private ?string $dataLabel = null,
        private ?string $host = null,
        private ?string $thread = null,
    ) {
    }

    public function getOriginalTest(): object
    {
        return $this->originalTest;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @return list<string>
     */
    public function getTitlePath(): array
    {
        return $this->titlePath;
    }

    public function getDataLabel(): ?string
    {
        return $this->dataLabel;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getThread(): ?string
    {
        return $this->thread;
    }
}
