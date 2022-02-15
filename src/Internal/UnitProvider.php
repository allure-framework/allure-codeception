<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use PHPUnit\Framework\TestCase;
use Qameta\Allure\Attribute\AttributeParser;
use Qameta\Allure\Setup\LinkTemplateCollectionInterface;
use Qameta\Allure\Model\ModelProviderInterface;
use Qameta\Allure\Model\Parameter;
use ReflectionException;
use ReflectionMethod;

use function array_shift;
use function is_int;

/**
 * @internal
 */
final class UnitProvider implements ModelProviderInterface
{
    /**
     * @param TestCase                        $test
     * @param LinkTemplateCollectionInterface $linkTemplates
     */
    public function __construct(
        private TestCase $test,
        LinkTemplateCollectionInterface $linkTemplates,
    ) {
    }

    /**
     * @param TestCase                        $test
     * @param LinkTemplateCollectionInterface $linkTemplates
     * @throws ReflectionException
     * @return list<ModelProviderInterface>
     */
    public static function createForChain(TestCase $test, LinkTemplateCollectionInterface $linkTemplates): array
    {
        /**
         * @var callable-string|null $methodOrFunction
         * @psalm-suppress InternalMethod
         */
        $methodOrFunction = $test->getName(false);

        return [
            ...AttributeParser::createForChain(
                classOrObject: $test,
                methodOrFunction: $methodOrFunction,
                linkTemplates: $linkTemplates,
            ),
            new self($test, $linkTemplates),
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

    /**
     * @throws ReflectionException
     */
    public function getParameters(): array
    {
        /** @psalm-suppress InternalMethod */
        if (!$this->test->usesDataProvider()) {
            return [];
        }

        $dataMethod = new ReflectionMethod($this->test, 'getProvidedData');
        $dataMethod->setAccessible(true);
        /** @psalm-suppress InternalMethod */
        $methodName = $this->test->getName(false);
        $testMethod = new ReflectionMethod($this->test, $methodName);
        $argNames = $testMethod->getParameters();

        $params = [];
        /**
         * @var array-key $key
         * @var mixed $param
         */
        foreach ($dataMethod->invoke($this->test) as $key => $param) {
            $argName = array_shift($argNames);
            $name = $argName?->getName() ?? $key;
            $params[] = new Parameter(
                is_int($name) ? "#$name" : $name,
                ArgumentAsString::get($param),
            );
        }

        return $params;
    }

    public function getDisplayName(): ?string
    {
        /** @psalm-suppress InternalMethod */
        return $this->test->getName();
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
