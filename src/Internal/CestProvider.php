<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Test\Cest;
use Qameta\Allure\Attribute\AttributeParser;
use Qameta\Allure\Setup\LinkTemplateCollectionInterface;
use Qameta\Allure\Model\ModelProviderInterface;
use Qameta\Allure\Model\Parameter;
use ReflectionException;

use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_int;
use function is_string;

/**
 * @internal
 */
final class CestProvider implements ModelProviderInterface
{
    public function __construct(
        private Cest $test,
    ) {
    }

    /**
     * @param Cest                            $test
     * @param LinkTemplateCollectionInterface $linkTemplates
     * @return list<ModelProviderInterface>
     * @throws ReflectionException
     */
    public static function createForChain(Cest $test, LinkTemplateCollectionInterface $linkTemplates): array
    {
        /** @psalm-var callable-string $callableTestMethod */
        $callableTestMethod = $test->getTestMethod();

        return [
            ...AttributeParser::createForChain(
                classOrObject: $test->getTestInstance(),
                methodOrFunction: $callableTestMethod,
                linkTemplates: $linkTemplates,
            ),
            new self($test),
        ];
    }

    public function getLinks(): array
    {
        return [];
    }

    public function getLabels(): array
    {
        return [];
    }

    public function getParameters(): array
    {
        /** @var mixed $currentExample */
        $currentExample = $this
            ->test
            ->getMetadata()
            ->getCurrent('example') ?? [];
        if (!is_array($currentExample)) {
            return [];
        }

        return array_map(
            fn (mixed $value, int|string $name) => new Parameter(
                is_int($name) ? "#$name" : $name,
                ArgumentAsString::get($value),
            ),
            array_values($currentExample),
            array_keys($currentExample),
        );
    }

    public function getDisplayName(): ?string
    {
        /** @psalm-var mixed $displayName */
        $displayName = $this->test->getName();

        return is_string($displayName)
            ? $displayName
            : null;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getDescriptionHtml(): ?string
    {
        return null;
    }

    /**
     * @psalm-suppress MixedOperand
     * @psalm-suppress MixedArgument
     */
    public function getFullName(): ?string
    {
        return $this->test->getTestInstance()::class . "::" . $this->test->getTestMethod();
    }
}
