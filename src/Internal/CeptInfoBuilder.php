<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Cept;

final class CeptInfoBuilder implements TestInfoBuilderInterface
{
    public function __construct(
        private Cept $test,
    ) {
    }

    public function build(?string $host, ?string $thread): TestInfo
    {
        return new TestInfo(
            originalTest: $this->test,
            signature: $this->test->getSignature(),
            class: $this->test->getName(),
            method: $this->test->getName(),
            host: $host,
            thread: $thread,
        );
    }
}
