<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Gherkin;

use function is_string;

final class GherkinInfoBuilder implements TestInfoBuilderInterface
{
    public function __construct(
        private Gherkin $test,
    ) {
    }

    public function build(?string $host, ?string $thread): TestInfo
    {
        return new TestInfo(
            originalTest: $this->test,
            signature: $this->test->getSignature(),
            class: $this->test->getFeature(),
            method: $this->test->getScenarioTitle(),
            host: $host,
            thread: $thread,
        );
    }
}
