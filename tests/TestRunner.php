<?php

class TestRunner
{
    private int $tests = 0;
    /** @var array<int, array{int, string, Throwable|null}> */
    private array $results = [];

    /**
     * @param callable():void $test
     */
    public function it(string $description, callable $test): void
    {
        $index = ++$this->tests;
        try {
            $test();
            $this->results[] = [$index, $description, null];
            $this->printResult($index, '✅', $description);
        } catch (Throwable $e) {
            $this->results[] = [$index, $description, $e];
            $this->printResult($index, '❌', $description);
        }
    }

    public function equals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $expectedString = var_export($expected, true);
            $actualString = var_export($actual, true);
            throw new RuntimeException($message !== '' ? $message : "Failed asserting that {$actualString} matches expected {$expectedString}.");
        }
    }

    public function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $expectedString = var_export($expected, true);
            $actualString = var_export($actual, true);
            throw new RuntimeException($message !== '' ? $message : "Failed asserting that {$actualString} is identical to {$expectedString}.");
        }
    }

    public function truthy(mixed $value, string $message = ''): void
    {
        if (!$value) {
            throw new RuntimeException($message !== '' ? $message : 'Failed asserting that value is truthy.');
        }
    }

    public function report(): int
    {
        echo PHP_EOL;
        $failures = array_filter(
            $this->results,
            static fn (array $result): bool => $result[2] instanceof Throwable
        );

        if ($failures === []) {
            printf("%d tests passed.\n", $this->tests);
            return 0;
        }

        printf("%d of %d tests failed:\n", count($failures), $this->tests);
        foreach ($failures as [$index, $description, $error]) {
            printf("- %s. %s: %s\n", $this->formatIndex($index), $description, $error->getMessage());
        }

        return 1;
    }

    private function printResult(int $index, string $symbol, string $description): void
    {
        printf("%s. %s %s\n", $this->formatIndex($index), $symbol, $description);
    }

    private function formatIndex(int $index): string
    {
        $digits = max(2, strlen((string) $index));
        return str_pad((string) $index, $digits, '0', STR_PAD_LEFT);
    }
}
