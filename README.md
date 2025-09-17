# Flow
##### Iterator Nirvana for PHP

## Runtime requirements

- PHP >= 8.0 (fully compatible with PHP 8.4)
- The Standard PHP Library (SPL), which ships with PHP by default

## Why fluent iterators?

Flow provides a fluent interface to assemble chains of iterators and other data processing operations. Instead of
collecting values into arrays and managing nested `foreach` loops manually, you compose small, intention-revealing
operations that stream data from one step to the next. The expressive syntax makes it **easy and enjoyable** to build
sophisticated processing pipelines while keeping memory usage low and the intent of each transformation crystal clear.

### Example: data crunching in one expression

```php
use PhpKit\Flow\Flow;

$topCustomers = Flow::from($orders)
    ->expand(fn ($order) => $order['line_items'])
    ->map(fn ($item) => $item['customer'])
    ->where(fn ($customer) => $customer['active'])
    ->mapAndFilter(function ($customer, &$key) {
        $key = $customer['id'];
        return $customer['total_spent'] > 1000 ? $customer : null;
    })
    ->sort('arsort')
    ->only(10)
    ->all();
```

The same workflow using temporary arrays and for-loops would be longer, harder to read and would duplicate values in
memory multiple times. A Flow pipeline keeps the computation streaming, lets you re-use SPL iterators seamlessly and
makes experimentation as simple as inserting or removing a step.

## Flow is **not** the usual Collection-style utility library

Typical Collection-like classes use arrays underneath. But `Flow`, even though it also uses a chainable fluent interface, is both an `Iterator` and a `Traversable` (therefore with native PHP support), and works perfectly with **Generators** and **SPL iterators**, with no arrays underneath. **Each iteration step only requires one value to be in memory (it can be generated on the fly), and the data is streamed through the pipeline**.

Since Flow implements both `Iterator` and `Traversable`, you can use a Flow object anywhere you would use a `Traversable` or `Iterator`. For example, you can use `foreach` directly over a Flow object:

```php
foreach (Flow::from([1, 2, 3]) as $value) {
    echo $value;
}
```

You can also pass Flow objects to any function that expects an `Iterator` or `Traversable`:

```php
// Flow works seamlessly with SPL functions
$count = iterator_count(Flow::from([1, 2, 3])->where(fn($x) => $x > 1));

// Or with any function expecting a Traversable
function processItems(Traversable $items) {
    foreach ($items as $item) {
        // process each item
    }
}

processItems(Flow::from($data)->map($transformer));
```

## Fluent operations reference

### Creating flows

| Method | Description |
| --- | --- |
| `new Flow($iterable = [])` | Instantiates a flow over any iterable, array or callable that produces values.
| `Flow::from($src)` | Convenience constructor that forwards to `new Flow($src)`.
| `Flow::range($from, $to, $step = 1)` | Streams a numeric range (inclusive) without allocating intermediate arrays.
| `Flow::sequence($list)` | Concatenates a list of iterables and iterates them sequentially.
| `Flow::combine($inputs, array $fields = null, int $flags = MultipleIterator::MIT_NEED_ANY | MultipleIterator::MIT_KEYS_ASSOC)` | Zips multiple iterables together, yielding keyed tuples of values.
| `Flow::void()` | Produces an empty flow, handy as a neutral element when composing.

### Combining and extending sequences

| Method | Description |
| --- | --- |
| `append($iterables)` | Appends one or more iterables after the current flow (optimised for nested `AppendIterator`s).
| `prepend($iterables)` | Prepends one or more iterables before the current flow.
| `prependValue($value, $key = 0)` | Inserts a single keyed value in front of the current iteration.
| `concat()` | Assumes each element of the flow is iterable and flattens the top level (similar to `sequence()` on the inside).
| `expand(callable $fn, bool $keepOriginals = false)` | Maps each value to an iterable and concatenates the resulting flows, optionally keeping the originals.
| `intercalate(callable $fn)` | Inserts generated values between the originals, then unfolds them.
| `unfold()` | Expands iterable values in place while streaming non-iterables untouched.
| `recursive(callable $childrenFn, int $mode = RecursiveIteratorIterator::SELF_FIRST)` | Wraps a recursive iterator, letting you walk tree structures effortlessly.
| `recursiveUnfold(callable $fn, bool $keepOriginals = false)` | Recursively maps values to sub-iterations and flattens the tree in one pass.
| `apply(string $traversableClass, ...$args)` | Wraps the current iterator with any Traversable class that accepts an iterator as first constructor argument.

### Transforming values

| Method | Description |
| --- | --- |
| `map(callable $fn, $arg = null)` | Transforms each item; the callback may update both value and key (by reference) and receives an optional argument.
| `mapAndFilter(callable $fn, $arg = null)` | Combines mapping and filtering: return a value to keep it, or `null` to drop it.
| `flip()` | Swaps keys with their corresponding values while iterating.
| `keys()` | Replaces each value by its key and reindexes the keys sequentially.
| `reindex(int $start = 0, int $step = 1)` | Rewrites keys as a numeric sequence without materializing the data.
| `regex(string $pattern, int $flags = 0, bool $useKeys = false)` | Replaces each item with the full set of regular expression matches.
| `regexExtract(string $pattern, int $flags = 0, bool $useKeys = false)` | Extracts the first regex match for each item.
| `regexMap(string $pattern, string $replaceWith, bool $useKeys = false)` | Performs regex replacements on the fly using `RegexIterator::setReplacement()`.
| `regexSplit(string $pattern, int $flags = 0, bool $useKeys = false)` | Splits strings by regex and yields the resulting fragments.
| `swap(callable $fn)` | Materializes the stream, lets a callback replace the dataset, then restarts iteration.
| `reduce(callable $fn, $seedValue = null)` | Collapses the flow into a single value by folding with an accumulator.

### Filtering, gating and flow control

| Method | Description |
| --- | --- |
| `where(callable $fn)` | Keeps only the items that satisfy the predicate.
| `whereMatch(string $pattern, int $flags = 0, bool $useKeys = false)` | Regex-powered filtering that tests either the values or the keys.
| `while_(callable $fn)` | Continues iteration until the callback returns `false`.
| `each(callable $fn)` | Executes a callback for every element (stop early by returning `false`).
| `repeat(int $times)` | Repeats the sequence a fixed number of times (or forever with a negative count).
| `repeatWhile(callable $fn)` | Replays the flow until the callback tells it to stop.
| `only(int $n)` | Limits the flow to the first *n* items, regardless of key types.
| `skip(int $n = 1)` | Skips the first *n* items and continues streaming.
| `drop(int $n = 1)` | Removes the last *n* items (materializes and trims the array).
| `slice(int $offset = 0, int $count = -1)` | Delegates to `LimitIterator` to take a window of items.
| `noRewind()` | Wraps the iterator with `NoRewindIterator` so it cannot be rewound after the first traversal.

### Ordering, caching and materialization

| Method | Description |
| --- | --- |
| `sort(string $type = 'sort', int $flags = SORT_REGULAR, ?callable $fn = null)` | Materializes and delegates to the native PHP sort family, preserving keys where appropriate.
| `reverse(bool $preserveKeys = false)` | Materializes, reverses and exposes the sequence through a generator.
| `cache()` | Memoizes the iterator so future traversals re-use cached values.
| `pack()` | Materializes and converts the flow into a zero-based array while retaining order.
| `all()` | Collects the entire dataset into an array, preserving keys.

### Inspecting and manual iteration helpers

| Method | Description |
| --- | --- |
| `fetch()` | Reads and advances a single value from the flow (auto-rewinds on first call).
| `fetchKey()` | Reads and advances a single key from the flow.
| `current(): mixed`, `key(): mixed`, `next(): void`, `rewind(): void`, `valid(): bool` | Implement the native `Iterator` interface so you can loop over `Flow` directly.
| `getIterator(): Iterator` | Returns the current iterator (materializing data if needed).
| `setIterator($iterable)` | Replaces the underlying iterator; intended for advanced scenarios.

### Filesystem flows

`FilesystemFlow` extends `Flow` with filesystem-specific factories and filters:

| Method | Description |
| --- | --- |
| `FilesystemFlow::from(string $path, int $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS)` | Streams directory entries with full control over SPL flags.
| `FilesystemFlow::glob(string $pattern, int $flags = 0)` | Iterates filesystem matches similar to `glob()` but lazily.
| `FilesystemFlow::recursiveFrom(string $path, int $flags = FilesystemIterator::KEY_AS_PATHNAME \| FilesystemIterator::CURRENT_AS_FILEINFO \| FilesystemIterator::SKIP_DOTS, int $mode = RecursiveIteratorIterator::SELF_FIRST)` | Builds a recursive traversal using `RecursiveIteratorIterator` and a configurable recursion mode.
| `FilesystemFlow::recursiveGlob(string $rootDir, string $pattern, int $flags = 0)` | Performs recursive glob searches, yielding `SplFileInfo` objects or paths according to flags.
| `onlyDirectories()` | Filters to directory entries only (requires `CURRENT_AS_FILEINFO`).
| `onlyFiles()` | Filters to file entries only (requires `CURRENT_AS_FILEINFO`).

## Iterator toolbox

Flow ships with a set of custom iterators that integrate seamlessly with SPL:

| Iterator | Purpose |
| --- | --- |
| `CachedIterator` | *Memoizes* another iterator so that subsequent traversals reuse cached values.
| `ConditionalIterator` | Iterates until a callback vetoes further processing.
| `FlipIterator` | Swaps keys for values (and optionally values for keys) while delegating iteration.
| `FunctionIterator` | Creates generators from callbacks so you can yield values without native generators.
| `HeadAndTailIterator` | Emits a head element followed by the iteration of its tail.
| `LoopIterator` | Repeats another iterator with configurable limits or callback-controlled looping.
| `MapIterator` | Applies a mapping callback lazily to another iterator.
| `RangeIterator` | Generates numeric progressions without building arrays.
| `RecursiveIterator` | A callback-driven recursive iterator that discovers children lazily.
| `ReduceIterator` | Folds values into a single result while still satisfying the iterator interface.
| `ReindexIterator` | Replaces keys with a generated numeric sequence.
| `SingleValueIterator` | Exposes exactly one value when an iterator is required.
| `UnfoldIterator` | Flattens nested iterables on demand, preserving original keys if desired.

## Helper functions

The `globals.php` helpers make it effortless to adopt Flow throughout an application:

- `flow($iterable): Flow` — shorthand for `Flow::from()`.
- `is_iterable()` polyfill — automatically defined when running on legacy PHP versions.
- `is_iterableEx($value): bool` — treats callables as iterables (they become `FunctionIterator`s).
- `iterator($value): Iterator` — converts arrays, traversables or callables into iterators, or returns an empty iterator.
- `iterable_to_array($value, bool $preserveKeys = true): array` — collects any iterable into an array.
- `array_mergeIterable(array &$target, $iterable, bool $preserveKeys = true): void` — merges another iterable into an array in place.
- `NOIT(): Iterator` — returns a shared empty iterator instance.

## Notes

Some operations (such as `reverse()` or `sort()`) need to materialize the stream into an array before continuing. The
library only buffers data when absolutely necessary and automatically returns to streaming mode afterwards.

## License

This library is open-source software licensed under the [MIT license](http://opensource.org/licenses/MIT).

**Flow** - Copyright &copy; 2015 Impactwave, Lda.
