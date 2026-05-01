<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Qameta\Allure\Codeception\Setup\ThreadDetectorInterface;

use function gethostname;

final class DefaultThreadDetector implements ThreadDetectorInterface
{
    private string|false|null $host = null;

    #[\Override]
    public function getHost(): ?string
    {
        $this->host ??= gethostname();

        return $this->host === false
            ? null
            : $this->host;
    }

    #[\Override]
    public function getThread(): ?string
    {
        return null;
    }
}
