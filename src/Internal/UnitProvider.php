<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Closure;
use Codeception\Test\TestCaseWrapper;
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
     * @param TestCaseWrapper                 $test
     * @param LinkTemplateCollectionInterface $linkTemplates
     */
    public function __construct(
        private TestCaseWrapper $test,
        LinkTemplateCollectionInterface $linkTemplates,
    ) {
    }

    /**
     * @param TestCaseWrapper                 $test
     * @param LinkTemplateCollectionInterface $linkTemplates
     * @throws ReflectionException
     * @return list<ModelProviderInterface>
     */
    public static function createForChain(TestCaseWrapper $test, LinkTemplateCollectionInterface $linkTemplates): array
    {
        $fields = $test->getReportFields();
        /** @var class-string $class */
        $class = $fields['class'] ?? null;
        /** @var Closure|callable-string|null $methodOrFunction */
        $methodOrFunction = $test->getMetadata()->getName();

        return [
            ...AttributeParser::createForChain(
                classOrObject: $class,
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
        $testMetadata = $this->test->getMetadata();
        if (null === $testMetadata->getIndex()) {
            return [];
        }

        $testCase = $this->test->getTestCase();

        $dataMethod = new ReflectionMethod($testCase, 'getProvidedData');
        $dataMethod->setAccessible(true);
        $methodName = $testMetadata->getName();
        $testMethod = new ReflectionMethod($testCase, $methodName);
        $argNames = $testMethod->getParameters();

        $params = [];
        /**
         * @var array-key $key
         * @var mixed $param
         */
        foreach ($dataMethod->invoke($testCase) as $key => $param) {
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
        return $this->test->getMetadata()->getName();
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
        return $this->test->getTestCase()::class . '::' . $this->test->getMetadata()->getName();
    }
}
