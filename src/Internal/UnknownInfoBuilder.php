<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

final class UnknownInfoBuilder implements TestInfoBuilderInterface
{
    public function __construct(
        private object $test,
    ) {
    }

    public function build(?string $host, ?string $thread): TestInfo
    {
        return new TestInfo(
            originalTest: $this->test,
            signature: 'Unknown test: ' . $this->test::class,
            host: $host,
            thread: $thread,
        );
    }
}
