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
        /** @psalm-var mixed $className */
        $className = $this->test->getFeature();
        /** @psalm-var mixed $methodName */
        $methodName = $this->test->getScenarioTitle();

        return new TestInfo(
            originalTest: $this->test,
            signature: (string) $this->test->getSignature(),
            class: is_string($className) ? $className : null,
            method: is_string($methodName) ? $methodName : null,
            host: $host,
            thread: $thread,
        );
    }
}
