<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestRunner.php';

use PhpKit\Flow\FilesystemFlow;
use PhpKit\Flow\Flow;
use PhpKit\Flow\Iterators\CachedIterator;
use PhpKit\Flow\Iterators\ConditionalIterator;
use PhpKit\Flow\Iterators\FlipIterator;
use PhpKit\Flow\Iterators\FunctionIterator;
use PhpKit\Flow\Iterators\HeadAndTailIterator;
use PhpKit\Flow\Iterators\LoopIterator;
use PhpKit\Flow\Iterators\MapIterator;
use PhpKit\Flow\Iterators\RangeIterator;
use PhpKit\Flow\Iterators\RecursiveIterator as FlowRecursiveIterator;
use PhpKit\Flow\Iterators\ReduceIterator;
use PhpKit\Flow\Iterators\ReindexIterator;
use PhpKit\Flow\Iterators\SingleValueIterator;
use PhpKit\Flow\Iterators\UnfoldIterator;

function flowTest_makeTempDir(): string
{
    $dir = sys_get_temp_dir() . '/flow-test-' . uniqid('', true);
    if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create temporary directory for Flow tests.');
    }
    return $dir;
}

function flowTest_removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $entries = scandir($dir);
    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            flowTest_removeDir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

$tests = new TestRunner();

// --- Flow construction helpers -------------------------------------------------

$tests->it('Flow accepts arrays, Traversables, and callables as sources', function () use ($tests): void {
    $fromArray = new Flow([1, 2, 3]);
    $tests->same([1, 2, 3], iterator_to_array($fromArray));

    $fromTraversable = Flow::from(new ArrayIterator(['x' => 10, 'y' => 20]));
    $tests->same(['x' => 10, 'y' => 20], $fromTraversable->all());

    $callableFlow = Flow::from(function (&$key): ?int {
        if ($key < 0) {
            return null;
        }
        return $key * 5;
    });
    $tests->same([], iterator_to_array($callableFlow), 'Callables default to empty when they terminate immediately.');
});

$tests->it('Static builders stitch iterables together', function () use ($tests): void {
    $tests->same([1, 2, 3], Flow::range(1, 3)->all());

    $sequence = Flow::sequence([
        [1, 2],
        new ArrayIterator([3, 4]),
        Flow::range(5, 5),
    ])->pack()->all();
    $tests->same([1, 2, 3, 4, 5], $sequence);

    $tests->same([], Flow::void()->all());
});

$tests->it('Flow::combine zips iterables and keeps keys aligned', function () use ($tests): void {
    $result = Flow::combine([
        ['Ada', 'Grace'],
        ['COBOL', 'Smalltalk', 'FORTRAN'],
    ], ['name', 'language'])->pack()->all();

    $tests->equals([
        ['name' => 'Ada', 'language' => 'COBOL'],
        ['name' => 'Grace', 'language' => 'Smalltalk'],
        ['name' => null, 'language' => 'FORTRAN'],
    ], $result);
});

// --- Core iterator manipulation -------------------------------------------------

$tests->it('append joins additional iterables', function () use ($tests): void {
    $flow = Flow::from([1])
        ->append([[2, 3], Flow::range(4, 5)])
        ->pack()
        ->all();

    $tests->same([1, 2, 3, 4, 5], $flow);
});

$tests->it('apply wraps the current iterator with an outer Traversable', function () use ($tests): void {
    $result = Flow::range(1, 5)->apply(LimitIterator::class, 0, 3)->all();
    $tests->same([1, 2, 3], $result);
});

$tests->it('cache memoises traversals of stateful iterables', function () use ($tests): void {
    $iterations = 0;
    $generator = (function () use (&$iterations) {
        foreach ([10, 20, 30] as $value) {
            $iterations++;
            yield $value;
        }
    })();

    $flow = Flow::from($generator)->cache();

    $firstPass = iterator_to_array($flow);
    $secondPass = iterator_to_array($flow);

    $tests->same([10, 20, 30], $firstPass);
    $tests->same($firstPass, $secondPass);
    $tests->same(3, $iterations, 'Cached iterator should only traverse the generator once.');
});

$tests->it('concat flattens nested iterables yielded by the stream', function () use ($tests): void {
    $result = Flow::from([
        [1, 2],
        new ArrayIterator([3, 4]),
        Flow::range(5, 6),
    ])->concat()->pack()->all();

    $tests->same([1, 2, 3, 4, 5, 6], $result);
});

$tests->it('drop removes trailing values', function () use ($tests): void {
    $tests->same([1, 2], Flow::from([1, 2, 3, 4])->drop(2)->all());
});

$tests->it('each visits items until the callback returns false', function () use ($tests): void {
    $visited = [];
    Flow::range(1, 5)->each(function (int $value) use (&$visited) {
        $visited[] = $value;
        return $value < 3;
    });

    $tests->same([1, 2, 3], $visited);
});

$tests->it('expand replaces values with iterables and flattens them', function () use ($tests): void {
    $doubled = Flow::from([1, 2])->expand(fn (int $value): array => [$value, $value * 10])->pack()->all();
    $tests->same([1, 10, 2, 20], $doubled);

    $withOriginals = Flow::from([1, 2])
        ->expand(fn (int $value): array => [$value * 10, $value * 100], true)
        ->pack()
        ->all();

    $tests->same([1, 10, 100, 2, 20, 200], $withOriginals);
});

$tests->it('fetch and fetchKey iterate one element at a time', function () use ($tests): void {
    $values = Flow::from(['alpha' => 1, 'beta' => 2]);
    $tests->same(1, $values->fetch());
    $tests->same(2, $values->fetch());
    $tests->same(false, $values->fetch());

    $keys = Flow::from(['alpha' => 1, 'beta' => 2]);
    $tests->same('alpha', $keys->fetchKey());
    $tests->same('beta', $keys->fetchKey());
    $tests->same(false, $keys->fetchKey());
});

$tests->it('flip swaps keys and values', function () use ($tests): void {
    $tests->same([1 => 'a', 2 => 'b'], Flow::from(['a' => 1, 'b' => 2])->flip()->all());
});

$tests->it('getIterator rehydrates materialised arrays as ArrayIterators', function () use ($tests): void {
    $flow = Flow::from([1, 2, 3]);
    $materialised = $flow->all();

    $iterator = $flow->getIterator();
    $tests->truthy($iterator instanceof ArrayIterator);
    $tests->same($materialised, iterator_to_array($iterator));
});

$tests->it('intercalate injects additional values after each element', function () use ($tests): void {
    $result = Flow::from(['a', 'b'])
        ->intercalate(fn (string $value): array => [$value . '!', strtoupper($value)])
        ->pack()
        ->all();

    $tests->same(['a', 'a!', 'A', 'b', 'b!', 'B'], $result);
});

$tests->it('Iterator primitives expose the underlying traversal state', function () use ($tests): void {
    $flow = Flow::from(['first' => 'A', 'second' => 'B']);
    $flow->rewind();

    $tests->same('first', $flow->key());
    $tests->same('A', $flow->current());
    $tests->truthy($flow->valid());

    $flow->next();
    $tests->same('second', $flow->key());
    $tests->same('B', $flow->current());

    $flow->next();
    $tests->truthy(!$flow->valid());
});

$tests->it('keys replaces values with their original keys and reindexes them', function () use ($tests): void {
    $tests->same([0 => 'first', 1 => 'second'], Flow::from(['first' => 10, 'second' => 20])->keys()->all());
});

$tests->it('map can mutate keys and share state via the third argument', function () use ($tests): void {
    $state = (object) ['history' => []];
    $result = Flow::from(['x' => 2, 'y' => 3])
        ->map(function (int $value, string &$key, object $history): int {
            $history->history[] = [$key, $value];
            $key = strtoupper($key);
            return $value * 10;
        }, $state)
        ->all();

    $tests->same(['X' => 20, 'Y' => 30], $result);
    $tests->same([['x', 2], ['y', 3]], $state->history);
});

$tests->it('mapAndFilter drops null results while still allowing key mutation', function () use ($tests): void {
    $state = (object) ['values' => []];
    $result = Flow::from(['a' => 1, 'b' => 2, 'c' => 3])
        ->mapAndFilter(function (int $value, string &$key, object $selected): ?int {
            if ($value % 2 === 0) {
                $selected->values[] = $value;
                $key = strtoupper($key);
                return $value * 100;
            }
            return null;
        }, $state)
        ->all();

    $tests->same(['B' => 200], $result);
    $tests->same([2], $state->values);
});

$tests->it('noRewind wraps the iterator so subsequent rewinds are ignored', function () use ($tests): void {
    $flow = Flow::from(new ArrayIterator([1, 2]))->noRewind();
    $iterator = $flow->getIterator();

    $iterator->rewind();
    $tests->same(1, $iterator->current());

    $iterator->next();
    $iterator->next();
    $iterator->rewind();
    $tests->truthy(!$iterator->valid());
});

$tests->it('only limits the number of items that flow through the pipeline', function () use ($tests): void {
    $tests->same([1, 2, 3], Flow::range(1, 10)->only(3)->pack()->all());
});

$tests->it('pack materialises values with sequential integer keys', function () use ($tests): void {
    $tests->same(['a', 'b'], Flow::from([2 => 'a', 4 => 'b'])->pack()->all());
});

$tests->it('prepend adds iterables in front of the existing chain', function () use ($tests): void {
    $result = Flow::from([3, 4])
        ->prepend([[1, 2], Flow::range(5, 5)])
        ->pack()
        ->all();

    $tests->same([1, 2, 5, 3, 4], $result);
});

$tests->it('prependValue injects a single element with a custom key', function () use ($tests): void {
    $result = Flow::from(['a' => 1])->prependValue(0, 'start')->all();
    $tests->same(['start' => 0, 'a' => 1], $result);
});

$tests->it('reduce collapses the stream into a single accumulated value', function () use ($tests): void {
    $result = Flow::from([1, 2, 3])->reduce(fn (int $prev, int $value): int => $prev + $value, 0)->all();
    $tests->same([6], $result);
});

$tests->it('recursive walks nested structures using RecursiveIteratorIterator modes', function () use ($tests): void {
    $tree = [
        ['name' => 'root', 'children' => [
            ['name' => 'left'],
            ['name' => 'right'],
        ]],
    ];

    $selfFirst = Flow::from($tree)
        ->recursive(function (array $node): ?array {
            return $node['children'] ?? null;
        })
        ->map(fn (array $node): string => $node['name'])
        ->pack()
        ->all();
    $tests->same(['root', 'left', 'right'], $selfFirst);

    $leavesOnly = Flow::from($tree)
        ->recursive(function (array $node): ?array {
            return $node['children'] ?? null;
        }, RecursiveIteratorIterator::LEAVES_ONLY)
        ->map(fn (array $node): string => $node['name'])
        ->pack()
        ->all();
    $tests->same(['left', 'right'], $leavesOnly);
});

$tests->it('recursiveUnfold replaces nodes with their expanded children', function () use ($tests): void {
    $tree = [
        ['name' => 'root', 'children' => [
            ['name' => 'leaf1'],
            ['name' => 'branch', 'children' => [
                ['name' => 'leaf2'],
            ]],
        ]],
    ];

    $expand = function (array $node) {
        if (isset($node['children'])) {
            return Flow::from($node['children']);
        }
        return $node['name'];
    };

    $leaves = Flow::from($tree)
        ->recursiveUnfold($expand)
        ->pack()
        ->all();
    $tests->same(['leaf1', 'leaf2'], $leaves);

    $withParents = Flow::from($tree)
        ->recursiveUnfold($expand, true)
        ->map(function ($value) {
            return is_array($value) ? $value['name'] : $value;
        })
        ->pack()
        ->all();
    $tests->same(['root', 'leaf1', 'branch', 'leaf2'], $withParents);
});

// --- Regular expression helpers -------------------------------------------------

$tests->it('regex collects all matches for each entry', function () use ($tests): void {
    $matches = Flow::from(['abc', 'axy'])
        ->regex('/a(.)/', PREG_PATTERN_ORDER)
        ->all();

    $tests->same([
        [
            ['ab'],
            ['b'],
        ],
        [
            ['ax'],
            ['x'],
        ],
    ], $matches);
});

$tests->it('regexExtract returns only the first match for each value or key', function () use ($tests): void {
    $values = Flow::from(['cat', 'dog'])->regexExtract('/(c)./')->pack()->all();
    $tests->same([
        ['ca', 'c'],
    ], $values);

    $keys = Flow::from(['abc' => 'value'])->regexExtract('/a/', 0, true)->all();
    $tests->same([
        'abc' => ['a'],
    ], $keys);
});

$tests->it('regexMap performs replacements against values or keys', function () use ($tests): void {
    $values = Flow::from(['rat', 'cat'])->regexMap('/a/', '[A]')->all();
    $tests->same(['r[A]t', 'c[A]t'], $values);

    $keys = Flow::from(['abc' => 'value'])->regexMap('/a/', 'X', true)->all();
    $tests->same(['Xbc' => 'value'], $keys);
});

$tests->it('regexSplit divides strings into pieces', function () use ($tests): void {
    $parts = Flow::from(['2024-05-31'])->regexSplit('/-/', 0)->all();
    $tests->same([
        ['2024', '05', '31'],
    ], $parts);
});

$tests->it('whereMatch filters entries using regular expressions', function () use ($tests): void {
    $result = Flow::from(['apple', 'banana', 'apricot'])->whereMatch('/^ap/')->pack()->all();
    $tests->same(['apple', 'apricot'], $result);
});

// --- Key and index manipulation -------------------------------------------------

$tests->it('reindex generates sequential keys with custom starting points and steps', function () use ($tests): void {
    $tests->same([5 => 'a', 7 => 'b'], Flow::from(['a', 'b'])->reindex(5, 2)->all());
});

$tests->it('repeat duplicates the underlying iterator the requested number of times', function () use ($tests): void {
    $result = Flow::from([1, 2])->repeat(2)->reindex()->all();
    $tests->same([1, 2, 1, 2], $result);
});

$tests->it('repeatWhile inspects the exhausted iterator before deciding to continue', function () use ($tests): void {
    $observed = [];
    $result = Flow::from([1, 2])
        ->repeatWhile(function ($value, $key) use (&$observed): bool {
            $observed[] = [$value, $key];
            return false;
        })
        ->pack()
        ->all();

    $tests->same([1, 2], $result);
    $tests->same([[null, null]], $observed);
});

$tests->it('reverse materialises data and can optionally preserve numeric keys', function () use ($tests): void {
    $tests->same([2, 1], Flow::from([1, 2])->reverse()->all());
    $tests->same([1 => 20, 0 => 10], Flow::from([10, 20])->reverse(true)->all());
});

$tests->it('setIterator swaps the underlying iterator mid-chain', function () use ($tests): void {
    $flow = Flow::from([1, 2]);
    $flow->setIterator([3, 4]);
    $tests->same([3, 4], $flow->all());
});

$tests->it('skip and slice provide offset-based trimming', function () use ($tests): void {
    $skipped = Flow::from([1, 2, 3, 4])->skip(2)->pack()->all();
    $tests->same([3, 4], $skipped);

    $sliced = Flow::from([1, 2, 3, 4])->slice(1, 2)->pack()->all();
    $tests->same([2, 3], $sliced);
});

$tests->it('sort materialises data, supports callbacks, and rejects unknown algorithms', function () use ($tests): void {
    $sortedByKey = Flow::from(['b' => 2, 'a' => 1])->sort('ksort')->all();
    $tests->same(['a' => 1, 'b' => 2], $sortedByKey);

    $custom = Flow::from(['first', 'second'])->sort('usort', SORT_REGULAR, fn (string $left, string $right): int => $right <=> $left)->all();
    $tests->same(['second', 'first'], $custom);

    try {
        Flow::from([1])->sort('not-a-sort');
        $tests->truthy(false, 'Expected an InvalidArgumentException for bad sort type.');
    } catch (InvalidArgumentException $exception) {
        $tests->truthy(str_contains($exception->getMessage(), 'Bad sort type'));
    }
});

$tests->it('swap materialises the data array and replaces it with the callback result', function () use ($tests): void {
    $result = Flow::from([1, 2, 3])->swap(fn (array $data): array => array_reverse($data))->all();
    $tests->same([3, 2, 1], $result);
});

$tests->it('unfold expands iterable values and streams their contents', function () use ($tests): void {
    $result = Flow::from([1, [2, 3], 4])->unfold()->pack()->all();
    $tests->same([1, 2, 3, 4], $result);
});

$tests->it('where filters values using a boolean predicate', function () use ($tests): void {
    $result = Flow::range(1, 6)->where(fn (int $value): bool => $value % 2 === 0)->pack()->all();
    $tests->same([2, 4, 6], $result);
});

$tests->it('while_ stops iteration as soon as the predicate fails', function () use ($tests): void {
    $result = Flow::range(1, 6)->while_(fn (int $value): bool => $value < 4)->pack()->all();
    $tests->same([1, 2, 3], $result);
});

// --- FilesystemFlow utilities ----------------------------------------------------

$tests->it('FilesystemFlow::from and glob enumerate directory contents', function () use ($tests): void {
    $dir = flowTest_makeTempDir();

    try {
        file_put_contents($dir . '/file.txt', 'file');
        mkdir($dir . '/sub');
        file_put_contents($dir . '/sub/nested.log', 'log');

        $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS;

        $entries = FilesystemFlow::from($dir, $flags)
            ->map(fn (SplFileInfo $info): string => $info->getFilename())
            ->pack()
            ->all();
        sort($entries);
        $tests->same(['file.txt', 'sub'], $entries);

        $matches = FilesystemFlow::glob($dir . '/*.txt')
            ->map(fn (SplFileInfo $info): string => $info->getFilename())
            ->pack()
            ->all();
        $tests->same(['file.txt'], $matches);
    } finally {
        flowTest_removeDir($dir);
    }
});

$tests->it('FilesystemFlow recursive helpers walk nested structures and filter entries', function () use ($tests): void {
    $dir = flowTest_makeTempDir();

    try {
        mkdir($dir . '/sub');
        mkdir($dir . '/sub/nested');
        file_put_contents($dir . '/file.txt', 'root');
        file_put_contents($dir . '/sub/inner.txt', 'inner');
        file_put_contents($dir . '/sub/nested/deep.txt', 'deep');

        $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS;
        $relative = function (SplFileInfo $info) use ($dir): string {
            $path = $info->getPathname();
            return ltrim(str_replace($dir, '', $path), DIRECTORY_SEPARATOR);
        };

        $all = FilesystemFlow::recursiveFrom($dir, $flags)
            ->map($relative)
            ->pack()
            ->all();
        sort($all);
        $tests->same(['file.txt', 'sub', 'sub/inner.txt', 'sub/nested', 'sub/nested/deep.txt'], $all);

        $directories = FilesystemFlow::recursiveFrom($dir, $flags)
            ->onlyDirectories()
            ->map($relative)
            ->pack()
            ->all();
        sort($directories);
        $tests->same(['sub', 'sub/nested'], $directories);

        $files = FilesystemFlow::recursiveFrom($dir, $flags)
            ->onlyFiles()
            ->map($relative)
            ->pack()
            ->all();
        sort($files);
        $tests->same(['file.txt', 'sub/inner.txt', 'sub/nested/deep.txt'], $files);

        $globbed = FilesystemFlow::recursiveGlob($dir, '*.txt')
            ->map($relative)
            ->pack()
            ->all();
        sort($globbed);
        $tests->same(['file.txt', 'sub/inner.txt', 'sub/nested/deep.txt'], $globbed);
    } finally {
        flowTest_removeDir($dir);
    }
});

// --- Global helper functions ----------------------------------------------------

$tests->it('flow() wraps values into Flow instances', function () use ($tests): void {
    $tests->same([1, 2], flow([1, 2])->all());
    $tests->same([], flow('scalar')->all());
});

$tests->it('is_iterable is always available and matches PHP semantics', function () use ($tests): void {
    $tests->truthy(function_exists('is_iterable'));
    $tests->truthy(is_iterable([1, 2, 3]));
    $tests->truthy(is_iterable(new ArrayIterator([4, 5])));
    $tests->truthy(!is_iterable(42));
});

$tests->it('is_iterableEx recognises arrays, Traversables, and callables', function () use ($tests): void {
    $tests->truthy(is_iterableEx([1]));
    $tests->truthy(is_iterableEx(new ArrayIterator()));
    $tests->truthy(is_iterableEx(fn (): int => 1));
    $tests->truthy(!is_iterableEx(123));
});

$tests->it('iterable_to_array materialises any iterable', function () use ($tests): void {
    $tests->same([1, 2, 3], iterable_to_array(Flow::range(1, 3)));
});

$tests->it('array_mergeIterable appends iterables to an array', function () use ($tests): void {
    $target = ['first' => 1];
    array_mergeIterable($target, ['second' => 2]);
    $tests->same(['first' => 1, 'second' => 2], $target);

    array_mergeIterable($target, Flow::range(3, 4), false);
    $tests->same(['first' => 1, 'second' => 2, 0 => 3, 1 => 4], $target);
});

$tests->it('iterator() returns the appropriate Traversable implementation', function () use ($tests): void {
    $arrayIterator = iterator([1, 2]);
    $tests->truthy($arrayIterator instanceof ArrayIterator);

    $innerIterator = new ArrayIterator([3, 4]);
    $tests->same($innerIterator, iterator($innerIterator));

    $aggregate = new class implements IteratorAggregate {
        public function getIterator(): Traversable
        {
            return new ArrayIterator(['inner' => 5]);
        }
    };
    $tests->same(['inner' => 5], iterator_to_array(iterator($aggregate)));

    $callableIterator = iterator(function (&$key, $prev) {
        if ($key < 1) {
            return $key;
        }
        return null;
    });
    $tests->truthy($callableIterator instanceof FunctionIterator);

    $tests->same(iterator('not iterable'), NOIT());
});

$tests->it('NOIT() returns a reusable empty iterator', function () use ($tests): void {
    $first = NOIT();
    $second = NOIT();

    $tests->truthy($first instanceof EmptyIterator);
    $tests->same([], iterator_to_array($first));
    $tests->same($first, $second);
});

$tests->it('Flow exposes the expected public API surface', function () use ($tests): void {
    $reflection = new ReflectionClass(Flow::class);
    $methods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === Flow::class
        )
    );
    sort($methods);

    $expected = [
        '__construct',
        'all',
        'append',
        'apply',
        'cache',
        'combine',
        'concat',
        'current',
        'drop',
        'each',
        'expand',
        'fetch',
        'fetchKey',
        'flip',
        'from',
        'getIterator',
        'intercalate',
        'key',
        'keys',
        'map',
        'mapAndFilter',
        'next',
        'noRewind',
        'only',
        'pack',
        'prepend',
        'prependValue',
        'range',
        'recursive',
        'recursiveUnfold',
        'reduce',
        'regex',
        'regexExtract',
        'regexMap',
        'regexSplit',
        'reindex',
        'repeat',
        'repeatWhile',
        'reverse',
        'rewind',
        'sequence',
        'setIterator',
        'skip',
        'slice',
        'sort',
        'swap',
        'unfold',
        'valid',
        'void',
        'where',
        'whereMatch',
        'while_',
    ];
    sort($expected);

    $tests->same($expected, $methods);
});

$tests->it('FilesystemFlow regression guards track every public method', function () use ($tests): void {
    $reflection = new ReflectionClass(FilesystemFlow::class);
    $methods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === FilesystemFlow::class
        )
    );
    sort($methods);

    $expected = ['from', 'glob', 'onlyDirectories', 'onlyFiles', 'recursiveFrom', 'recursiveGlob'];
    sort($expected);

    $tests->same($expected, $methods);
});

$tests->it('Iterator helper coverage stays in sync with the source tree', function () use ($tests): void {
    $expectedClasses = [
        CachedIterator::class,
        ConditionalIterator::class,
        FlipIterator::class,
        FunctionIterator::class,
        HeadAndTailIterator::class,
        LoopIterator::class,
        MapIterator::class,
        RangeIterator::class,
        FlowRecursiveIterator::class,
        ReduceIterator::class,
        ReindexIterator::class,
        SingleValueIterator::class,
        UnfoldIterator::class,
    ];
    $discovered = [];
    foreach (glob(__DIR__ . '/../src/Flow/Iterators/*.php') as $file) {
        $discovered[] = 'PhpKit\\Flow\\Iterators\\' . basename($file, '.php');
    }
    sort($expectedClasses);
    sort($discovered);
    $tests->same($expectedClasses, $discovered);

    $methodExpectations = [
        CachedIterator::class => ['current', 'getInnerIterator', 'key', 'next', 'rewind', 'valid'],
        ConditionalIterator::class => ['__construct', 'valid'],
        FlipIterator::class => ['__construct', 'current', 'key'],
        FunctionIterator::class => ['__construct', 'current', 'key', 'next', 'rewind', 'valid'],
        HeadAndTailIterator::class => ['__construct', 'current', 'key', 'next', 'rewind', 'valid'],
        LoopIterator::class => ['limit', 'next', 'repeat', 'test', 'valid'],
        MapIterator::class => ['__construct', 'current', 'key', 'valid'],
        RangeIterator::class => ['__construct', 'current', 'key', 'next', 'rewind', 'valid'],
        FlowRecursiveIterator::class => ['__construct', 'getChildren', 'hasChildren'],
        ReduceIterator::class => ['__construct', 'current', 'key', 'next', 'rewind', 'valid'],
        ReindexIterator::class => ['__construct', 'key', 'next', 'rewind'],
        SingleValueIterator::class => ['__construct', 'current', 'key', 'next', 'rewind', 'valid'],
        UnfoldIterator::class => ['__construct', 'current', 'getInnerIterator', 'key', 'next', 'nextOuter', 'rewind', 'valid'],
    ];

    foreach ($methodExpectations as $class => $expectedMethods) {
        $reflection = new ReflectionClass($class);
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            array_filter(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class
            )
        );
        sort($methods);
        sort($expectedMethods);
        $tests->same($expectedMethods, $methods);
    }
});

$tests->it('Global helper functions remain defined', function () use ($tests): void {
    $expectedFunctions = ['array_mergeIterable', 'flow', 'is_iterable', 'is_iterableEx', 'iterable_to_array', 'iterator', 'NOIT'];
    foreach ($expectedFunctions as $function) {
        $tests->truthy(function_exists($function), 'Missing global helper: ' . $function);
    }
});

// --- Iterator helper classes ----------------------------------------------------

$tests->it('CachedIterator caches the first traversal and replays it on subsequent passes', function () use ($tests): void {
    $source = new ArrayIterator([1, 2, 3]);
    $cached = new CachedIterator($source);

    $tests->same([1, 2, 3], iterator_to_array($cached));
    $cached->rewind();
    $inner = $cached->getInnerIterator();
    $tests->truthy($inner instanceof ArrayIterator);
    $tests->same([1, 2, 3], iterator_to_array($inner));
    $tests->same([1, 2, 3], iterator_to_array($cached));
});

$tests->it('ConditionalIterator stops when its predicate fails', function () use ($tests): void {
    $filtered = new ConditionalIterator(new ArrayIterator([1, 2, 3, 4]), fn (int $value): bool => $value < 3);
    $tests->same([1, 2], iterator_to_array($filtered));
});

$tests->it('FlipIterator can exchange keys and values independently', function () use ($tests): void {
    $flippedValues = new FlipIterator(new ArrayIterator(['a' => 1, 'b' => 2]));
    $tests->same([1 => 'a', 2 => 'b'], iterator_to_array($flippedValues));

    $flippedKeysOnly = new FlipIterator(new ArrayIterator(['a' => 1, 'b' => 2]), false, true);
    $tests->same([1 => 1, 2 => 2], iterator_to_array($flippedKeysOnly));

    $flippedValuesOnly = new FlipIterator(new ArrayIterator(['a' => 1, 'b' => 2]), true, false);
    $tests->same(['a' => 'a', 'b' => 'b'], iterator_to_array($flippedValuesOnly));
});

$tests->it('FunctionIterator tracks keys while invoking the callback on each valid check', function () use ($tests): void {
    $iterator = new FunctionIterator(function (&$key, $prev) {
        if ($key < 3) {
            return $key * 10;
        }
        return null;
    });

    $iterator->rewind();
    $tests->truthy(!$iterator->valid());
    $tests->same(0, $iterator->current());
    $tests->same(0, $iterator->key());

    $iterator->next();
    $tests->truthy(!$iterator->valid());
    $tests->same(10, $iterator->current());
    $tests->same(1, $iterator->key());

    $iterator->next();
    $tests->truthy(!$iterator->valid());
    $tests->same(20, $iterator->current());
    $tests->same(2, $iterator->key());

    $iterator->next();
    $tests->truthy($iterator->valid());
    $tests->same(null, $iterator->current());
});

$tests->it('HeadAndTailIterator yields the head value followed by a tail sequence', function () use ($tests): void {
    $tail = new ArrayIterator(['k1' => 'tail1', 'k2' => 'tail2']);
    $iterator = new HeadAndTailIterator('head', $tail, 'h', true);

    $tests->same(['h' => 'head', 'k1' => 'tail1', 'k2' => 'tail2'], iterator_to_array($iterator));
});

$tests->it('LoopIterator supports repetition limits and custom predicates', function () use ($tests): void {
    $looped = new LoopIterator(new ArrayIterator([1, 2]));
    $looped->repeat(2);
    $tests->same([1, 2, 1, 2], iterator_to_array($looped, false));

    $limited = new LoopIterator(new ArrayIterator([1, 2]));
    $limited->repeat(5)->limit(3);
    $tests->same([1, 2, 1], iterator_to_array($limited, false));

    $observed = [];
    $conditional = new LoopIterator(new ArrayIterator([1, 2]));
    $conditional->test(function ($value, $key) use (&$observed): bool {
        $observed[] = [$value, $key];
        return false;
    });
    $tests->same([1, 2], iterator_to_array($conditional, false));
    $tests->same([[null, null]], $observed);
});

$tests->it('MapIterator transforms values while exposing key references and shared state', function () use ($tests): void {
    $state = (object) ['keys' => []];
    $iterator = new MapIterator(new ArrayIterator(['x' => 1, 'y' => 2]), function (int $value, string &$key, object $history): int {
        $history->keys[] = $key;
        $key = strtoupper($key);
        return $value * 10;
    }, $state);

    $tests->same(['X' => 10, 'Y' => 20], iterator_to_array($iterator));
    $tests->same(['x', 'y'], $state->keys);
});

$tests->it('RangeIterator iterates inclusively in either direction', function () use ($tests): void {
    $asc = new RangeIterator(1, 3, 1);
    $tests->same([0 => 1, 1 => 2, 2 => 3], iterator_to_array($asc));

    $desc = new RangeIterator(3, 1, -1);
    $tests->same([0 => 3, 1 => 2, 2 => 1], iterator_to_array($desc));
});

$tests->it('RecursiveIterator exposes children discovered via the callback', function () use ($tests): void {
    $tree = [
        ['name' => 'root', 'children' => [
            ['name' => 'leaf'],
        ]],
    ];

    $iterator = new FlowRecursiveIterator(new ArrayIterator($tree), function (array $node): ?array {
        return $node['children'] ?? null;
    });

    $iterator->rewind();
    $tests->truthy($iterator->hasChildren());

    $children = $iterator->getChildren();
    $children->rewind();
    $tests->same('leaf', $children->current()['name']);
    $tests->truthy(!$children->hasChildren());
});

$tests->it('ReduceIterator emits exactly one accumulated result', function () use ($tests): void {
    $iterator = new ReduceIterator(new ArrayIterator([1, 2, 3]), fn (int $prev, int $value): int => $prev + $value, 0);
    $tests->same([0 => 6], iterator_to_array($iterator));
});

$tests->it('ReindexIterator rewrites keys using the configured range', function () use ($tests): void {
    $iterator = new ReindexIterator(new ArrayIterator(['first' => 'a', 'second' => 'b']), 10, 5);
    $tests->same([10 => 'a', 15 => 'b'], iterator_to_array($iterator));
});

$tests->it('SingleValueIterator yields one value and then terminates', function () use ($tests): void {
    $iterator = new SingleValueIterator('value');
    $iterator->rewind();
    $tests->truthy($iterator->valid());
    $tests->same('value', $iterator->current());

    $iterator->next();
    $tests->truthy(!$iterator->valid());
});

$tests->it('UnfoldIterator expands nested iterables while optionally preserving keys', function () use ($tests): void {
    $iterator = new UnfoldIterator([1, ['a' => 2, 'b' => 3], 4], UnfoldIterator::USE_ORIGINAL_KEYS);
    $inner = $iterator->getInnerIterator();
    $tests->same([1, ['a' => 2, 'b' => 3], 4], iterator_to_array($inner));
    $tests->same([0 => 1, 'a' => 2, 'b' => 3, 2 => 4], iterator_to_array($iterator));

    $withEmpty = new UnfoldIterator([[], ['k' => 'value'], 7]);
    $withEmpty->rewind();
    $withEmpty->nextOuter();
    $tests->truthy($withEmpty->valid());
    $tests->same('value', $withEmpty->current());
    $tests->same([0 => 1, 'a' => 2, 'b' => 3, 2 => 4], iterator_to_array($iterator));
});

exit($tests->report());
