<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\TestCaseWrapper;

use function is_int;
use function preg_match;

final class UnitInfoBuilder implements TestInfoBuilderInterface
{
    public function __construct(
        private TestCaseWrapper $test,
    ) {
    }

    public function build(?string $host, ?string $thread): TestInfo
    {
        $fields = $this->test->getReportFields();
        $index = $this->test->getMetadata()->getIndex();
        $dataLabel = is_int($index) ? "#$index" : $index;

        return new TestInfo(
            originalTest: $this->test,
            signature: $this->test->getSignature(),
            class: $fields['class'] ?? null,
            method: $this->test->getMetadata()->getName(),
            dataLabel: $dataLabel,
            host: $host,
            thread: $thread,
        );
    }
}
