<?php

declare(strict_types=1);

namespace Qameta\Allure\Codeception\Test\Unit;

use Codeception\Test\Unit;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function dirname;
use function fclose;
use function file_get_contents;
use function getenv;
use function glob;
use function implode;
use function is_array;
use function is_resource;
use function json_encode;
use function mkdir;
use function preg_split;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function str_starts_with;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

/**
 * Runs hook-failure fixtures in a Codeception subprocess and asserts
 * Allure fixtures + globals.
 *
 * Aggregate Module (+ beforeClass folded in) phases only — cannot split
 * Module vs @beforeClass with the extension alone.
 *
 * Not covered (no Codeception events):
 * - Cest `_before` / `_after` / `@before` / `@after` (inside Cest::test())
 * - Unit setUp/tearDown (inside PHPUnit case)
 * - Step hooks / Module `_failed`
 */
final class HookFailureIntegrationTest extends Unit
{
    /**
     * @dataProvider providerHookFailures
     */
    public function testHookFailure_EmitsFixtureAndSinglePrefixedGlobal(
        string $hookName,
        string $mode,
        string $messagePrefix,
        string $fixtureStatus,
    ): void {
        $outputDir = sys_get_temp_dir() . '/allure-codeception-hook-int-' . uniqid('', true);
        mkdir($outputDir);

        [$output, $exitCode] = $this->runHookSuite($outputDir, $hookName, $mode);
        self::assertNotSame(0, $exitCode, implode("\n", $output));

        $globals = $this->readGlobalMessages($outputDir);
        $matching = array_values(array_filter(
            $globals,
            static fn (string $message): bool => str_starts_with($message, $messagePrefix),
        ));
        self::assertCount(
            1,
            $matching,
            sprintf(
                "Expected one global starting with %s, got: %s\nCodecept output:\n%s",
                $messagePrefix,
                (string) json_encode($globals),
                implode("\n", $output),
            ),
        );

        $fixtures = $this->readFixturesNamed($outputDir, $hookName);
        self::assertNotEmpty($fixtures, "Expected fixture named {$hookName}\n" . implode("\n", $output));
        $statuses = array_map(
            static fn (array $fixture): ?string => $fixture['status'] ?? null,
            $fixtures,
        );
        self::assertContains($fixtureStatus, $statuses, (string) json_encode($fixtures));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerHookFailures(): iterable
    {
        yield 'before throw' => ['_before', 'throw', '_before failed', 'broken'];
        yield 'after throw' => ['_after', 'throw', '_after failed', 'broken'];
        yield 'beforeSuite throw' => ['_beforeSuite', 'throw', '_beforeSuite failed', 'broken'];
        yield 'afterSuite throw' => ['_afterSuite', 'throw', '_afterSuite failed', 'broken'];
        yield 'before assertion' => ['_before', 'assert', '_before failed', 'failed'];
        yield 'before empty message' => ['_before', 'empty', '_before failed', 'broken'];
    }

    /**
     * Runs the hooks-fixtures suite with env overrides (OS-safe; no shell VAR=value).
     *
     * @return array{0: list<string>, 1: int}
     */
    private function runHookSuite(string $outputDir, string $hookName, string $mode): array
    {
        $root = dirname(__DIR__, 3);
        $cmd = [
            PHP_BINARY,
            $root . '/vendor/bin/codecept',
            'run',
            'unit',
            '-c',
            $root . '/codeception-hooks.yml',
            '--no-colors',
        ];

        $env = array_merge(
            getenv(),
            [
                'ALLURE_HOOK_OUTPUT' => $outputDir,
                'ALLURE_HOOK_FAIL' => $hookName,
                'ALLURE_HOOK_MODE' => $mode,
            ],
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, $root, $env);
        self::assertTrue(is_resource($proc), 'Failed to start codecept subprocess');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        $combined = trim(($stdout === false ? '' : $stdout) . "\n" . ($stderr === false ? '' : $stderr));
        $split = $combined === '' ? false : preg_split("/\r\n|\n|\r/", $combined);
        $lines = $split === false ? [] : $split;

        return [$lines, $exitCode];
    }

    /**
     * @return list<string>
     */
    private function readGlobalMessages(string $outputDir): array
    {
        $messages = [];
        $files = glob($outputDir . '/*-globals.json');
        if (!is_array($files)) {
            return [];
        }

        foreach ($files as $file) {
            /** @var array{errors?: list<array{message?: string}>} $data */
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            foreach ($data['errors'] ?? [] as $error) {
                if (isset($error['message'])) {
                    $messages[] = $error['message'];
                }
            }
        }

        return $messages;
    }

    /**
     * @return list<array{name?: string, status?: string}>
     */
    private function readFixturesNamed(string $outputDir, string $hookName): array
    {
        $fixtures = [];
        $files = glob($outputDir . '/*-container.json');
        if (!is_array($files)) {
            return [];
        }

        foreach ($files as $file) {
            /**
             * @var array{
             *     befores?: list<array{name?: string, status?: string}>,
             *     afters?: list<array{name?: string, status?: string}>
             * } $data
             */
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            foreach ([...($data['befores'] ?? []), ...($data['afters'] ?? [])] as $fixture) {
                if (($fixture['name'] ?? null) === $hookName) {
                    $fixtures[] = $fixture;
                }
            }
        }

        return $fixtures;
    }
}
