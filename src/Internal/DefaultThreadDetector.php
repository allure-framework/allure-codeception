<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Qameta\Allure\Codeception\Setup\ThreadDetectorInterface;

use function gethostname;

final class DefaultThreadDetector implements ThreadDetectorInterface
{
    private string|false|null $host = null;

    public function getHost(): ?string
    {
        $this->host ??= gethostname();

        return $this->host === false
            ? null
            : $this->host;
    }

    public function getThread(): ?string
    {
        return null;
    }
}
