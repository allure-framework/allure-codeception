<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Qameta\Allure\Attribute\AttributeParser;
use Qameta\Allure\Setup\LinkTemplateCollectionInterface;
use Qameta\Allure\Model\Label;
use Qameta\Allure\Model\ModelProviderInterface;
use ReflectionException;

/**
 * @internal
 */
final class SuiteProvider implements ModelProviderInterface
{
    public function __construct(
        private ?SuiteInfo $suiteInfo,
    ) {
    }

    /**
     * @param SuiteInfo|null                  $suiteInfo
     * @param LinkTemplateCollectionInterface $linkTemplates
     * @return list<ModelProviderInterface>
     * @throws ReflectionException
     */
    public static function createForChain(
        ?SuiteInfo $suiteInfo,
        LinkTemplateCollectionInterface $linkTemplates,
    ): array {
        $providers = [new self($suiteInfo)];
        $suiteClass = $suiteInfo?->getClass();

        return isset($suiteClass)
            ? [
                ...$providers,
                ...AttributeParser::createForChain(classOrObject: $suiteClass, linkTemplates: $linkTemplates),
            ]
            : $providers;
    }

    public function getLinks(): array
    {
        return [];
    }

    public function getLabels(): array
    {
        return [
            Label::language(null),
            Label::framework('codeception'),
            Label::parentSuite($this->suiteInfo?->getName()),
            Label::package($this->suiteInfo?->getName()),
        ];
    }

    public function getParameters(): array
    {
        return [];
    }

    public function getDisplayName(): ?string
    {
        return null;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getDescriptionHtml(): ?string
    {
        return null;
    }

    public function getFullName(): ?string
    {
        return null;
    }
}
