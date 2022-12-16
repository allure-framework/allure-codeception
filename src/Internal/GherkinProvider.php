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

    public function getLinks(): array
    {
        return [];
    }

    public function getLabels(): array
    {
        return array_map(
            fn (string $value) => Label::feature($value),
            [
                ...array_values($this->test->getFeatureNode()->getTags()),
                ...array_values($this->test->getScenarioNode()->getTags()),
            ],
        );
    }

    public function getParameters(): array
    {
        return [];
    }

    public function getDisplayName(): ?string
    {
        return $this->test->toString();
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
