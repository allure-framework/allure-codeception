<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Cest;

use function is_int;
use function is_object;
use function is_string;

final class CestInfoBuilder implements TestInfoBuilderInterface
{

    public function __construct(
        private Cest $test,
    ) {
    }

    public function build(?string $host, ?string $thread): TestInfo
    {
        /** @var mixed $testClass */
        $testClass = $this->test->getTestClass();
        /** @var mixed $testMethod */
        $testMethod = $this->test->getTestMethod();

        return new TestInfo(
            originalTest: $this->test,
            signature: (string) $this->test->getSignature(),
            class: is_object($testClass) ? $testClass::class : null,
            method: is_string($testMethod) ? $testMethod : null,
            dataLabel: $this->getDataLabel(),
            host: $host,
            thread: $thread,
        );
    }

    private function getDataLabel(): ?string
    {
        /** @psalm-var mixed $index */
        $index = $this->test->getMetadata()->getIndex();

        if (is_string($index)) {
            return $index;
        }
        if (is_int($index)) {
            return "#$index";
        }

        return null;
    }
}
