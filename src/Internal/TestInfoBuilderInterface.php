<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

interface TestInfoBuilderInterface
{
    public function build(?string $host, ?string $thread): TestInfo;
}
