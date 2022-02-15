<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use PHPUnit\Framework\TestCase;

use function preg_match;

final class UnitInfoBuilder implements TestInfoBuilderInterface
{
    public function __construct(
        private TestCase $test,
    ) {
    }

    public function build(?string $host, ?string $thread): TestInfo
    {
        /** @psalm-suppress InternalMethod */
        $methodName = $this->test->getName(false);

        return new TestInfo(
            originalTest: $this->test,
            signature: $this->test::class . ':' . $methodName,
            class: $this->test::class,
            method: $methodName,
            dataLabel: $this->getDataLabel(),
            host: $host,
            thread: $thread,
        );
    }

    private function getDataLabel(): ?string
    {
        /** @psalm-suppress InternalMethod */
        $dataSet = $this->test->getDataSetAsString(false);

        return 1 === preg_match('#^ with data set (.+)$#', $dataSet, $matches)
            ? $matches[1]
            : null;
    }
}
