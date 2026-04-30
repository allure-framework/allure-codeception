<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Gherkin;

use function array_pop;
use function realpath;

final class GherkinInfoBuilder implements TestInfoBuilderInterface
{
    public function __construct(
        private string $rootDir,
        private Gherkin $test,
    ) {
    }

    #[\Override]
    public function build(?string $host, ?string $thread): TestInfo
    {
        // May contain .. if the config is not in a parent directory.
        $filePath = realpath($this->test->getFileName());

        /**
         * @var list<string>
         */
        $titlePath = ModelFunctions::getTitlePathByFile($this->rootDir, $filePath);

        $featureName = $this->test->getFeature();
        if ($featureName) {
            // Prefer a more human-readable feature name instead of the filename.
            if ($titlePath) {
                array_pop($titlePath);
                $titlePath[] = $featureName;
            } else {
                $titlePath = [$featureName];
            }
        }

        return new TestInfo(
            originalTest: $this->test,
            signature: $this->test->getSignature(),
            class: $this->test->getFeature(),
            method: $this->test->getScenarioTitle(),
            titlePath: $titlePath,
            host: $host,
            thread: $thread,
        );
    }
}
