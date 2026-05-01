<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Gherkin;
use Qameta\Allure\Model\Label;
use Qameta\Allure\Model\ModelProviderInterface;

use function array_map;
use function array_values;

/**
 * @internal
 */
final class GherkinProvider implements ModelProviderInterface
{
    public function __construct(
        private Gherkin $test,
    ) {
    }

    public static function createForChain(Gherkin $test): array
    {
        return [new self($test)];
    }

    #[\Override]
    public function getLinks(): array
    {
        return [];
    }

    #[\Override]
    public function getLabels(): array
    {
        return array_map(
            fn (string $value) => Label::feature($value),
            [
                ...$this->test->getFeatureNode()->getTags(),
                ...$this->test->getScenarioNode()->getTags(),
            ],
        );
    }

    #[\Override]
    public function getParameters(): array
    {
        return [];
    }

    #[\Override]
    public function getDisplayName(): ?string
    {
        return $this->test->toString();
    }

    #[\Override]
    public function getDescription(): ?string
    {
        return null;
    }

    #[\Override]
    public function getDescriptionHtml(): ?string
    {
        return null;
    }

    #[\Override]
    public function getFullName(): ?string
    {
        return null;
    }
}
