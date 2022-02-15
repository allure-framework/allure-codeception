<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Qameta\Allure\Model\Label;
use Qameta\Allure\Model\ModelProviderInterface;

final class TestInfoProvider implements ModelProviderInterface
{
    public function __construct(
        private TestInfo $info,
    ) {
    }

    /**
     * @param TestInfo $info
     * @return list<ModelProviderInterface>
     */
    public static function createForChain(TestInfo $info): array
    {
        return [new self($info)];
    }

    public function getLinks(): array
    {
        return [];
    }

    public function getLabels(): array
    {
        return [
            Label::testClass($this->info->getClass()),
            Label::testMethod($this->info->getMethod()),
            Label::host($this->info->getHost()),
            Label::thread($this->info->getThread()),
        ];
    }

    public function getParameters(): array
    {
        return [];
    }

    public function getDisplayName(): ?string
    {
        return null;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getDescriptionHtml(): ?string
    {
        return null;
    }

    public function getFullName(): ?string
    {
        return null;
    }
}
