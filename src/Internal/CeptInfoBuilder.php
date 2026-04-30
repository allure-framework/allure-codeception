<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Cept;

use function array_pop;
use function realpath;

final class CeptInfoBuilder implements TestInfoBuilderInterface
{
    public function __construct(
        private string $rootDir,
        private Cept $test,
    ) {
    }

    #[\Override]
    public function build(?string $host, ?string $thread): TestInfo
    {
        // May contain .. if the config is not in a parent directory.
        $filePath = realpath($this->test->getFileName());
        $titlePath = ModelFunctions::getTitlePathByFile($this->rootDir, $filePath);

        // A cept file is a single test, so we're removing the file node from titlePath.
        array_pop($titlePath);

        return new TestInfo(
            originalTest: $this->test,
            signature: $this->test->getSignature(),
            class: $this->test->getName(),
            method: $this->test->getName(),
            titlePath: $titlePath,
            host: $host,
            thread: $thread,
        );
    }
}
