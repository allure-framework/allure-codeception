<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Cept;
use Qameta\Allure\Setup\LinkTemplateCollectionInterface;
use Qameta\Allure\Model\Label;
use Qameta\Allure\Model\Link;
use Qameta\Allure\Model\LinkType;
use Qameta\Allure\Model\ModelProviderInterface;

use function array_filter;
use function array_map;
use function array_merge;
use function array_pop;
use function array_values;
use function explode;
use function is_array;
use function is_string;
use function str_replace;
use function trim;

/**
 * @internal
 */
final class CeptProvider implements ModelProviderInterface
{
    private bool $isLoaded = false;

    /**
     * @var list<Label>
     */
    private array $legacyLabels = [];

    /**
     * @var list<Link>
     */
    private array $legacyLinks = [];

    private ?string $legacyTitle = null;

    private ?string $legacyDescription = null;

    /**
     * @param Cept                            $test
     * @param LinkTemplateCollectionInterface $linkTemplates
     */
    public function __construct(
        private Cept $test,
        private LinkTemplateCollectionInterface $linkTemplates,
    ) {
    }

    /**
     * @param Cept                            $test
     * @param LinkTemplateCollectionInterface $linkTemplates
     * @return list<ModelProviderInterface>
     */
    public static function createForChain(Cept $test, LinkTemplateCollectionInterface $linkTemplates): array
    {
        return [new self($test, $linkTemplates)];
    }

    public function getLinks(): array
    {
        $this->loadLegacyModels();

        return $this->legacyLinks;
    }

    public function getLabels(): array
    {
        $this->loadLegacyModels();

        return $this->legacyLabels;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function getDisplayName(): ?string
    {
        $this->loadLegacyModels();

        if (isset($this->legacyTitle)) {
            return $this->legacyTitle;
        }

        /** @psalm-var mixed $testName */
        $testName = $this->test->getName();

        return is_string($testName)
            ? $testName
            : null;
    }

    public function getFullName(): ?string
    {
        return $this->test->getSignature();
    }

    public function getDescription(): ?string
    {
        $this->loadLegacyModels();

        return $this->legacyDescription;
    }

    public function getDescriptionHtml(): ?string
    {
        return null;
    }

    private function getLegacyAnnotation(string $name): ?string
    {
        /**
         * @psalm-var mixed $annotations
         * @psalm-suppress InvalidArgument
         */
        $annotations = $this->test->getMetadata()->getParam($name);
        if (!is_array($annotations)) {
            return null;
        }
        /** @var mixed $lastAnnotation */
        $lastAnnotation = array_pop($annotations);

        return is_string($lastAnnotation)
            ? $this->getStringFromTagContent(trim($lastAnnotation, '()'))
            : null;
    }

    /**
     * @param string $name
     * @return list<string>
     */
    private function getLegacyAnnotations(string $name): array
    {
        /**
         * @psalm-var mixed $annotations
         * @psalm-suppress InvalidArgument
         */
        $annotations = $this->test->getMetadata()->getParam($name);
        $stringAnnotations = is_array($annotations)
            ? array_values(array_filter($annotations, 'is_string'))
            : [];

        return array_merge(
            ...array_map(
                fn (string $annotation) => $this->getStringsFromTagContent(trim($annotation, '()')),
                $stringAnnotations,
            ),
        );
    }

    private function loadLegacyModels(): void
    {
        if ($this->isLoaded) {
            return;
        }
        $this->isLoaded = true;

        $this->legacyTitle = $this->getLegacyAnnotation('Title');
        $this->legacyDescription = $this->getLegacyAnnotation('Description');
        $this->legacyLabels = [
            ...array_map(
                fn (string $value): Label => Label::feature($value),
                $this->getLegacyAnnotations('Features'),
            ),
            ...array_map(
                fn (string $value): Label => Label::story($value),
                $this->getLegacyAnnotations('Stories'),
            ),
        ];
        $linkTemplate = $this->linkTemplates->get(LinkType::issue()) ?? null;
        $this->legacyLinks = array_map(
            fn (string $value): Link => Link::issue($value, $linkTemplate?->buildUrl($value)),
            $this->getLegacyAnnotations('Issues'),
        );
    }

    private function getStringFromTagContent(string $tagContent): string
    {
        return str_replace('"', '', $tagContent);
    }

    /**
     * @param string $string
     * @return list<string>
     */
    private function getStringsFromTagContent(string $string): array
    {
        $detected = str_replace(['{', '}', '"'], '', $string);

        return explode(',', $detected);
    }
}
