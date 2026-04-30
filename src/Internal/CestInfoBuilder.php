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

    #[\Override]
    public function build(?string $host, ?string $thread): TestInfo
    {
        $class = $this->test->getTestInstance()::class;
        $titlePath = ModelFunctions::getTitlePathByClass($class);

        return new TestInfo(
            originalTest: $this->test,
            signature: $this->test->getSignature(),
            class: $class,
            method: $this->test->getTestMethod(),
            titlePath: $titlePath,
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
