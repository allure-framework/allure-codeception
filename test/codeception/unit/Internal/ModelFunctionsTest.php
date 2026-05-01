<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit\Internal;

use Codeception\Test\Unit;
use Qameta\Allure\Codeception\Internal\ModelFunctions;

use function getcwd;
use function str_replace;

use const DIRECTORY_SEPARATOR;

final class ModelFunctionsTest extends Unit
{
    /**
     * @dataProvider providerGetTitlePathByFile
     */
    public function testGetTitlePathByFile(string $base, string $path, array $expectedTitlePath): void
    {
        // Quick Windows hack
        $base = str_replace("/", DIRECTORY_SEPARATOR, $base);
        $path = str_replace("/", DIRECTORY_SEPARATOR, $path);

        self::assertSame($expectedTitlePath, ModelFunctions::getTitlePathByFile($base, $path));

        // Trailing separator must be ignored on base
        self::assertSame($expectedTitlePath, ModelFunctions::getTitlePathByFile($base . DIRECTORY_SEPARATOR, $path));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerGetTitlePathByFile(): iterable
    {
        return [
            "Direct child path" => ["/foo", "/foo/bar", ["bar"]],
            "Nested child path" => ["/foo/bar", "/foo/bar/baz/qux", ["baz", "qux"]],
            "Sibling path" => ["/foo/bar", "/foo/baz", ["baz"]],
            "Path several levels up" => ["/foo/bar/baz", "/foo/qux", ["qux"]],
            "Identical paths" => ["/foo/bar", "/foo/bar", []],
            "Common root only" => ["/", "/foo/bar", ["foo", "bar"]],
        ];
    }

    /**
     * @dataProvider providerGetTitlePathByFileWithFallback
     */
    public function testGetTitlePathByFile_FallbackToCwd(
        string $base,
        string $cwdRelPath,
        array $expectedTitlePath
    ): void {
        // Quick Windows hack
        $base = str_replace("/", DIRECTORY_SEPARATOR, $base);
        $cwdRelPath = str_replace("/", DIRECTORY_SEPARATOR, $cwdRelPath);
        $cwd = getcwd();

        self::assertIsString($cwd);

        $path = $cwd . DIRECTORY_SEPARATOR . $cwdRelPath;

        self::assertSame($expectedTitlePath, ModelFunctions::getTitlePathByFile($base, $path));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerGetTitlePathByFileWithFallback(): iterable
    {
        return [
            "Different trees" => ["BAD:/foo", "foo", ["foo"]],
            "Relative base" => ["foo", "foo/bar", ["foo", "bar"]],
            "Empty base" => ["", "foo/bar", ["foo", "bar"]],
        ];
    }

    /**
     * @dataProvider providerGetTitlePathByClass
     */
    public function testGetTitlePathByClass(?string $class, array $expectedTitlePath): void
    {
        self::assertSame($expectedTitlePath, ModelFunctions::getTitlePathByClass($class));
    }

    /**
     * @return iterable<string, array{?string, list<string>}>
     */
    public static function providerGetTitlePathByClass(): iterable
    {
        return [
            "No namespace" => ["foo", ["foo"]],
            "Single-level namespace" => ["foo\\bar", ["foo", "bar"]],
            "Nested namespaces" => ["foo\\bar\\baz", ["foo", "bar", "baz"]],
            "Fully qualified name" => ["\\foo\\bar\\baz", ["foo", "bar", "baz"]],
            "Empty string" => ["", []],
            "Null" => [null, []],
        ];
    }
}
