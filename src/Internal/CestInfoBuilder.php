<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Cest;

use function is_int;
use function is_string;

final class CestInfoBuilder implements TestInfoBuilderInterface
{
    public function __construct(
        private Cest $test,
    ) {
    }

    public function build(?string $host, ?string $thread): TestInfo
    {
        return new TestInfo(
            originalTest: $this->test,
            signature: $this->test->getSignature(),
            class: $this->test->getTestInstance()::class,
            method: $this->test->getTestMethod(),
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
