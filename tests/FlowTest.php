<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

$tests->it('Flow::range builds inclusive number sequences', function () use ($tests): void {
    $tests->equals([1, 2, 3], \PhpKit\Flow\Flow::range(1, 3)->all());
    $tests->equals([3, 2, 1], \PhpKit\Flow\Flow::range(3, 1, -1)->all());
});

$tests->it('Fluent map pipelines transform and filter values', function () use ($tests): void {
    $result = \PhpKit\Flow\Flow::from(['a' => 1, 'b' => 2, 'c' => 3])
        ->map(fn ($value, &$key) => $key . $value)
        ->mapAndFilter(function (string $value) {
            return str_contains($value, '2') ? strtoupper($value) : null;
        })
        ->pack()
        ->all();

    $tests->same(['B2'], $result);
});

$tests->it('Flow::combine zips multiple iterables and keeps keys in sync', function () use ($tests): void {
    $result = \PhpKit\Flow\Flow::combine([
        ['Ada', 'Grace'],
        ['COBOL', 'Smalltalk', 'FORTRAN'],
    ], ['name', 'language'])->pack()->all();

    $tests->equals([
        ['name' => 'Ada', 'language' => 'COBOL'],
        ['name' => 'Grace', 'language' => 'Smalltalk'],
        ['name' => null, 'language' => 'FORTRAN'],
    ], $result);
});

$tests->it('regexMap performs replacements without touching PHP internals', function () use ($tests): void {
    $result = \PhpKit\Flow\Flow::from(['rat', 'cat'])->regexMap('/a/', '[A]')->all();
    $tests->same(['r[A]t', 'c[A]t'], $result);
});

$tests->it('cache reuses computed values for subsequent traversals', function () use ($tests): void {
    $iterations = 0;
    $generator = (function () use (&$iterations) {
        foreach ([10, 20, 30] as $value) {
            $iterations++;
            yield $value;
        }
    })();

    $flow = \PhpKit\Flow\Flow::from($generator)->cache();

    $firstPass = iterator_to_array($flow);
    $secondPass = iterator_to_array($flow);

    $tests->same([10, 20, 30], $firstPass);
    $tests->same($firstPass, $secondPass);
    $tests->same(3, $iterations, 'Cached iterator should only traverse the generator once.');
});

exit($tests->report());
