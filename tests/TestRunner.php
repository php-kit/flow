<?php

class TestRunner
{
    private int $tests = 0;
    /** @var array<int, array{string, Throwable}> */
    private array $failures = [];

    /**
     * @param callable():void $test
     */
    public function it(string $description, callable $test): void
    {
        $this->tests++;
        try {
            $test();
            echo '.';
        } catch (Throwable $e) {
            $this->failures[] = [$description, $e];
            echo 'F';
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
        echo PHP_EOL . PHP_EOL;
        if ($this->failures === []) {
            printf("%d tests passed.\n", $this->tests);
            return 0;
        }

        printf("%d of %d tests failed:\n", count($this->failures), $this->tests);
        foreach ($this->failures as [$description, $error]) {
            printf("- %s: %s\n", $description, $error->getMessage());
        }

        return 1;
    }
}
