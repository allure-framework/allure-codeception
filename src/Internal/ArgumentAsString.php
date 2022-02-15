<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Internal;

use Codeception\Util\Locator;
use Stringable;

use function array_map;
use function class_exists;
use function is_a;
use function is_array;
use function is_object;
use function is_resource;
use function is_string;
use function json_encode;
use function strtr;
use function trim;

/**
 * @internal
 */
final class ArgumentAsString implements Stringable
{
    public function __construct(
        private mixed $argument,
    ) {
    }

    public static function get(mixed $argument): string
    {
        return (string) new self($argument);
    }

    private function prepareArgument(mixed $argument): mixed
    {
        return match (true) {
            is_string($argument) => $this->prepareString($argument),
            is_resource($argument) => $this->prepareResource($argument),
            is_array($argument) => $this->prepareArray($argument),
            is_object($argument) => $this->prepareObject($argument),
            default => $argument,
        };
    }

    private function prepareString(string $argument): string
    {
        return strtr($argument, ["\n" => '\n', "\r" => '\r', "\t" => ' ']);
    }

    /**
     * @param resource $argument
     * @return string
     */
    private function prepareResource($argument): string
    {
        return (string) $argument;
    }

    private function prepareArray(array $argument): array
    {
        return array_map(
            fn (mixed $element): mixed => $this->prepareArgument($element),
            $argument,
        );
    }

    private function prepareObject(object $argument): string
    {
        if (isset($argument->__mocked) && is_object($argument->__mocked)) {
            $argument = $argument->__mocked;
        }
        if ($argument instanceof Stringable) {
            return (string) $argument;
        }

        $webdriverByClass = '\Facebook\WebDriver\WebDriverBy';
        if (class_exists($webdriverByClass) && is_a($argument, $webdriverByClass)) {
            return Locator::humanReadableString($argument);
        }

        return trim($argument::class, "\\");
    }

    public function __toString(): string
    {
        return json_encode(
            $this->prepareArgument($this->argument),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
