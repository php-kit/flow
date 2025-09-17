<?php
namespace PhpKit\Flow;

use AppendIterator;
use ArrayIterator;
use CallbackFilterIterator;
use EmptyIterator;
use InvalidArgumentException;
use Iterator;
use LimitIterator;
use MultipleIterator;
use NoRewindIterator;
use PhpKit\Flow\Iterators\CachedIterator;
use PhpKit\Flow\Iterators\ConditionalIterator;
use PhpKit\Flow\Iterators\FlipIterator;
use PhpKit\Flow\Iterators\HeadAndTailIterator;
use PhpKit\Flow\Iterators\LoopIterator;
use PhpKit\Flow\Iterators\MapIterator;
use PhpKit\Flow\Iterators\RangeIterator;
use PhpKit\Flow\Iterators\RecursiveIterator;
use PhpKit\Flow\Iterators\ReduceIterator;
use PhpKit\Flow\Iterators\ReindexIterator;
use PhpKit\Flow\Iterators\UnfoldIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Provides a fluent interface to assemble chains of iterators and other data processing operations.
 *
 * <p>The fluent API makes it **very easy and intuitive** to use the native SPL iterators (together with some custom
 * ones provided by this library) and the expressive syntax allows you to assemble sophisticated processing pipelines
 * in an elegant, terse and readable fashion.
 *
 * <p>An iteration chain assembled with this builder can perform multiple transformations over a data flow without
 * storing in memory the resulting data from intermediate steps. When operating over large data sets, this mechanism
 * can be very light on memory consumption.
 *
 * <p>Inputs to the chain can be any kind of **iterables**. Iterables are things that can be converted to iterators.
 * They can be native arrays, {@see Traversable}s (classes implementing the native {@see Iterator} or
 * {@see IteratorAggregate} intefaces), invoked generator functions (on PHP>=5.5) or, additionaly, callables (ex.
 * {@see Closure}s),
 * which will be converted to {@see FunctionIterator} instances, allowing you to write generator function look-alikes
 * on PHP<5.5.
 *
 * <p>Note: Some operations require the iteration data to be "materialized", i.e. fully iterated and stored internally
 * as an array, before the operation is applied. This only happens for operations that require all data to be present
 * (ex: `reverse()` or `sort()`), and the resulting data will be automatically converted back to an iterator whenever
 * it makes sense.
 */
class Flow implements Iterator
{
  private static $SORT_TYPES = [
    'asort'       => 2,
    'arsort'      => 2,
    'krsort'      => 2,
    'ksort'       => 2,
    'natcasesort' => 1,
    'natsort'     => 1,
    'rsort'       => 2,
    'shuffle'     => 1,
    'sort'        => 2,
    'uasort'      => 3,
    'uksort'      => 3,
    'usort'       => 3,
  ];
  /** @var array */
  private $data;
  /**
   * Used by `fetch()` and `fetchKey()`.
   *
   * @var bool
   */
  private $fetching;
  /** @var Iterator */
  private $it;

  /**
   * Sets the initial data/iterator.
   *
   * @param mixed $src An iterable,
   */
  function __construct ($src = [])
  {
    $this->setIterator ($src);
  }

  /**
   * Creates a Flow from a list of iterable inputs that iterates over all of them in parallel.
   *
   * The generated sequence is comprised of array items where each one contains an iteration step from each one of the
   * inputs. The key for the value from each input corresponds (by default) to the original key for the
   * input on the original set, or it may be fetched from the `$fields` argument, if one is provided.
   *
   * The iteration continues until all inputs are exhausted, returning `null` fields for prematurely finished inputs.
   *
   * You can change these behaviours using the `$flags` argument.
   *
   * @param mixed      $inputs A sequence of iterable inputs.
   * @param array|null $fields A list or map of key names for use on the resulting array items. If not
   *                           specified, the original iterator's keys will be used or numerical autoincremented
   *                           indexes, depending on the `$flags`.
   * @param int        $flags  One or more of the MultipleIterator::MIT_XXX constants.
   *                           <p>Default: MIT_NEED_ANY | MIT_KEYS_ASSOC
   * @return static
   */
  static function combine ($inputs, ?array $fields = null, $flags = 2)
  {
    $mul = new MultipleIterator($flags);
    foreach (iterator ($inputs) as $k => $it)
      $mul->attachIterator (iterator ($it), isset($fields) ? $fields[$k] : $k);
    return new static ($mul);
  }

  /**
   * @param mixed $src An iterable.
   * @return static
   */
  static function from ($src)
  {
    return new static ($src);
  }

  /**
   * Creates an iteration over a generated sequence of numbers.
   *
   * @param int|float $from Starting value.
   * @param int|float $to   The (inclusive) limit. May be lower than `$from` if the `$step` is negative.
   * @param int|float $step Can be either positive or negative. If zero, an infinite sequence of constant values is
   *                        generated.
   * @return static
   */
  static function range ($from, $to, $step = 1)
  {
    return new static (new RangeIterator ($from, $to, $step));
  }

  /**
   * Concatenates the specified iterables and iterates them in sequence.
   *
   * >**Caution**
   *   <p><br>When using {@see iterator_to_array()} to copy the values of the resulting sequence into an array, you
   *   have to set the optional `use_key` argument to `FALSE`.<br>
   *   When `use_key` is not `FALSE` any keys reoccuring in inner iterators will get overwritten in the returned array.
   *   <p><br>When using {@see Flow::all()} the same problem may occur.<br>
   *   You can use {@see Flow::pack()} or {@see Flow::reindex()} instead, if you need a monotonically increasing
   *   sequence of keys.
   *
   * @param mixed $list A sequence of iterables.
   * @return static
   */
  static function sequence ($list)
  {
    $a = new AppendIterator;
    foreach (iterator ($list) as $it)
      $a->append (iterator ($it));
    return new static ($a);
  }

  /**
   * Creates an empty iteration.
   *
   * @return static
   */
  static function void ()
  {
    return new static (new EmptyIterator);
  }

  /**
   * Materializes the current iterator chain into an array.
   *
   * <p>It preserves the original keys (unlike {@see Flow::pack()}).
   *
   * ><p>Beware of issues with concatenated iterators that generate the same keys. See {@see append()}
   *
   * @return array
   */
  function all ()
  {
    if (!isset ($this->data))
      $this->data = iterator_to_array ($this->it);
    return $this->data;
  }

  /**
   * Appends one or more iterators to the current one and sets a new iterator that iterates over all of them.
   *
   * <p>**Note:** an optimization is performed if the current iterator is already an {@see AppendIterator}.
   *
   * > ##### Caution
   *   When using {@see iterator_to_array()} to copy the values of the resulting sequence into an array, you
   *   have to set the optional `use_key` argument to `FALSE`.<br>
   *   When `use_key` is not `FALSE` any keys reoccuring in inner iterators will get overwritten in the returned array.
   *   <p><br>When using {@see Flow::all()} the same problem may occur.<br>
   *   You can use {@see Flow::pack()} or {@see Flow::reindex()} instead, if you need a monotonically increasing
   *   sequence of keys.
   *
   * @param mixed $list A sequence of iterables to append.
   * @return $this
   */
  function append ($list)
  {
    $cur = $this->getIterator ();
    if ($cur instanceof AppendIterator) {
      foreach (iterator ($list) as $it)
        $cur->append (iterator ($it));
    }
    else {
      $a = new AppendIterator;
      $a->append ($cur);
      foreach (iterator ($list) as $it)
        $a->append (iterator ($it));
      $this->setIterator ($a);
    }
    return $this;
  }

  /**
   * Instantiates an outer iterator and chains it to the current iterator.
   *
   * Ex:
   * ```
   *   Query::range (1,10)->apply ('RegexIterator', '/^1/')->all ()
   * ```
   *
   * @param string $traversableClass The name of a {@see Traversable} class whose constructor receives an
   *                                 iterator as a first argument.
   * @param mixed  ...$args          Additional arguments for the external iterator's constructor.
   * @return $this
   */
  function apply ($traversableClass)
  {
    $args    = func_get_args ();
    $args[0] = $this->getIterator ();
    $c       = new \ReflectionClass ($traversableClass);
    $this->setIterator ($c->newInstanceArgs ($args));
    return $this;
  }

  /**
   * *Memoize* the current iterator's values so that subsequent iterations will not need to iterate it again.
   *
   * @return $this
   */
  function cache ()
  {
    $this->setIterator (new CachedIterator ($this->getIterator ()));
    return $this;
  }

  /**
   * Assumes the current iterator is a sequence of iterables and sets a new iterator that iterates over all of them.
   *
   * >**Caution**
   *   <p><br>When using {@see iterator_to_array()} to copy the values of the resulting sequence into an array, you
   *   have to set the optional `use_key` argument to `FALSE`.<br>
   *   When `use_key` is not `FALSE` any keys reoccuring in inner iterators will get overwritten in the returned array.
   *   <p><br>When using {@see Flow::all()} the same problem may occur.<br>
   *   You can use {@see Flow::pack()} or {@see Flow::reindex()} instead, if you need a monotonically increasing
   *   sequence of keys.
   *
   * @return $this
   */
  function concat ()
  {
    $a = new AppendIterator;
    foreach ($this->getIterator () as $it)
      $a->append (iterator ($it));
    $this->setIterator ($a);
    return $this;
  }

  public function current (): mixed
  {
    return $this->it->current ();
  }

  /**
   * Drops the last `$n` elements from the iteration.
   *
   * Note: this also materializes the data and reindexes it.
   *
   * @param int $n
   * @return $this
   */
  function drop ($n = 1)
  {
    $this->pack ();
    $this->data = array_slice ($this->data, 0, -$n);
    return $this;
  }

  /**
   * Calls a function for every element in the iterator.
   *
   * @param callable $fn A callback that receives the current value and key; it can, optionally, return `false` to break
   *                     the loop.
   * @return $this
   */
  function each (callable $fn)
  {
    foreach ($this->getIterator () as $k => $v)
      if ($fn ($v, $k) === false) break;
    return $this;
  }

  /**
   * Expands values from the current iteration into their own iterations and provides a new iteration that concatenates
   * all of them.
   *
   * <p>This is done by replacing each value on the current iteration by the values generated by a new iterator
   * returned by a user-supplied function called for each of the original values.
   * <br>This is equivalent to a `map($fn)->unfold()` operation.
   *
   * <p>Alternatively, by specifying `true` for the second argument, instead of each item being replaced by its
   * expansion, it may instead reamain on the iteration, being the expanded values inserted between it and the next
   * value on the original sequence.
   * <br>That is equivalent to a `intercalate($fn)->unfold()` operation.
   *
   * @param callable $fn            A callback that receives the current outer iterator item's value and key and
   *                                returns the corresponding inner iterable.
   * @param bool     $keepOriginals If set, the original value is prepended to each expanded sequence.
   * @return $this
   */
  function expand (callable $fn, $keepOriginals = false)
  {
    $keepOriginals ? $this->intercalate ($fn) : $this->map ($fn);
    return $this->unfold ();
  }

  /**
   * Gets the current value from the composite iterator and iterates to the next.
   *
   * This is a shortcut way of reading one single data item from the iteration.
   * It also takes care of rewinding the iterator when called for the first time.
   *
   * @return mixed|false `false` when the iteration is finished.
   */
  function fetch ()
  {
    $it = $this->getIterator ();
    if (!$this->fetching) {
      $this->fetching = true;
      $it->rewind ();
    }
    if ($it->valid ()) {
      $v = $it->current ();
      $it->next ();
      return $v;
    }
    return false;
  }

  /**
   * Gets the current key from the composite iterator and iterates to the next.
   *
   * This is a shortcut way of reading one single data item from the iteration.
   * It also takes care of rewinding the iterator when called for the first time.
   *
   * @return mixed|false `false` when the iteration is finished.
   */
  function fetchKey ()
  {
    $it = $this->getIterator ();
    if (!$this->fetching) {
      $this->fetching = true;
      $it->rewind ();
    }
    if ($it->valid ()) {
      $k = $it->key ();
      $it->next ();
      return $k;
    }
    return false;
  }

  /**
   * Swaps values for the corresponding keys.
   *
   * @return $this
   */
  function flip ()
  {
    $this->setIterator (new FlipIterator ($this->getIterator ()));
    return $this;
  }

  /**
   * (PHP 5 &gt;= 5.0.0)<br/>
   * Retrieves an external iterator.
   * <p>This allows instances of this class to be iterated (ex. on foreach loops).
   * <p>This is also used internally to make sure any array data stored internally is converted to an iterator before
   * being used.
   * > <p>**WARNING:** don't forget to call `rewind()` before reading from the iterator.
   *
   * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
   * @return Iterator
   */
  public function getIterator (): Iterator
  {
    if (isset ($this->data)) {
      $this->it = new ArrayIterator ($this->data);
      unset ($this->data);
    }
    return $this->it;
  }

  /**
   * Appends to each item a new one computed by the specified function. This doubles the size of the iteration set.
   *
   * <p>**Note:** the resulting iteration may have duplicate keys. Use {@see reindex()} to normalize them.
   *
   * @param callable $fn    A callback that receives both the value and the key of the item being iterated on the
   *                        original iteration sequence and returns the new value to be intercalated.
   * @return Flow
   */
  function intercalate (callable $fn)
  {
    return $this->map (function ($v, &$k) use ($fn) {
      $r = $fn ($v, $k);
      if (is_iterable ($r))
        return new HeadAndTailIterator ($v, $r, $k, true);
      return $r;
    })->unfold ();
  }

  public function key (): mixed
  {
    return $this->it->key ();
  }

  /**
   * Replaces values by the corresponding keys and sets the keys to increasing integer indexes starting from 0.
   *
   * @return $this
   */
  function keys ()
  {
    $this->setIterator (new FlipIterator ($this->getIterator (), true));
    return $this->reindex ();
  }

  /**
   * Transforms the iterated data using a callback function.
   *
   * @param callable $fn  A callback that receives a value, a key and an option extra argument, and returns the new
   *                      value.<br> It can also receive the key by reference and change it.
   *                      <p>Ex:<code>  ->map (function ($v, &$k) { $k = $k * 10; return $v * 100; })</code>
   * @param mixed    $arg An optional extra argument to be passed to the callback on every iteration.
   *                      <p>The callback can change the argument if it declares the parameter as a reference.
   * @return $this
   */
  function map (callable $fn, $arg = null)
  {
    $this->setIterator (new MapIterator ($this->getIterator (), $fn, $arg));
    return $this;
  }

  /**
   * Transforms each input data item, optionally filtering it out.
   *
   * @param callable $fn  A callback that receives a value and key and returns a new value or `null` to discard it.<br>
   *                      It can also receive the key by reference and change it.
   *                      <p>Ex:<code>  ->mapAndFilter (function ($v,&$k) { $k=$k*10; return $v>5? $v*100:null;})</code>
   * @param mixed    $arg An optional extra argument to be passed to the callback on every iteration.
   *                      <p>The callback can change the argument if it declares the parameter as a reference.
   * @return $this
   */
  function mapAndFilter (callable $fn, $arg = null)
  {
    $this->setIterator (new CallbackFilterIterator (new MapIterator ($this->getIterator (), $fn, $arg), function ($v) {
      return isset ($v);
    }));
    return $this;
  }

  public function next (): void
  {
    $this->it->next ();
  }

  /**
   * Makes the iteration non-rewindable.
   *
   * @return $this
   */
  function noRewind ()
  {
    $this->setIterator (new NoRewindIterator($this->getIterator ()));
    return $this;
  }

  /**
   * Iterates only the first `$n` items.
   * <p>If `$n >` iterator count or `$n < 0`, this will have no effect.
   *
   * Note: this method is not equivalent to `limit (0, $n)`, for it can handle any kind of keys.
   *
   * @param int $n How many iterations, at most.
   * @return $this
   */
  function only ($n)
  {
    $this->setIterator ($it = new LoopIterator ($this->getIterator ()));
    $it->limit ($n);
    return $this;
  }

  /**
   * Materializes and reindexes the current data into a series of sequential integer keys.
   *
   * <p>Access {@see all()} on the result to get the resulting array.
   *
   * <p>This is useful to extract the data as a linear array with no discontinuous keys.
   *
   * ><p>This is faster than {@see reindex()} bit it materializes the data. This should usually be the last
   * operation to perform before retrieving the results.
   *
   * @return $this
   */
  function pack ()
  {
    $this->data = isset ($this->data) ? array_values ($this->data) : iterator_to_array ($this->it, false);
    return $this;
  }

  /**
   * Prepends one or more iterators to the current one and sets a new iterator that iterates over all of them.
   *
   * > ##### Caution
   *   See {@see append()}
   *
   * @param mixed $list A sequence of iterables to prepend.
   * @return $this
   */
  function prepend ($list)
  {
    $a = new AppendIterator;
    foreach (iterator ($list) as $it)
      $a->append (iterator ($it));
    $a->append ($this->getIterator ());
    $this->setIterator ($a);
    return $this;
  }

  /**
   * Prepends a single value to the current iteration and sets a new iterator that iterates over that value and all
   * values of the previously set iterator.
   *
   * > ##### Caution
   *   See {@see append()}
   *
   * @param mixed $value The value to be prepended.
   * @param mixed $key   The key to be prepended.
   * @return $this
   */
  function prependValue ($value, $key = 0)
  {
    $this->setIterator (new HeadAndTailIterator ($value, $this->getIterator (), $key, true));
    return $this;
  }

  /**
   * Wraps a recursive iterator over the current iterator.
   *
   * @param callable $fn   A callback that receives the current node's value, key and nesting depth, and returns an
   *                       iterable for the node's children or `null` if the node has no children.
   * @param int      $mode One of the constants from RecursiveIteratorIterator:
   *                       <p> 0 = LEAVES_ONLY
   *                       <p> 1 = SELF_FIRST (default)
   *                       <p> 2 = CHILD_FIRST
   * @return $this
   */
  function recursive (callable $fn, $mode = RecursiveIteratorIterator::SELF_FIRST)
  {
    $this->setIterator (new RecursiveIteratorIterator (new RecursiveIterator ($this->getIterator (), $fn), $mode));
    return $this;
  }

  /**
   * Maps some or all iteration values to sub-iterations recursively and unfolds them into a single iteration.
   *
   * <p>This is an alternative way to recursively iterate nested structures. Note that values that map to iterables
   * will not show up themselves on the final iteration unless `$keepOriginals` is `true` (ex: on a filesystem
   * iteration, directories themselves will not be listed, only their contents, unless the mapper returns an iterable
   * that also contains the directory or `$keepOriginals == true`).
   *
   * <p>**Note:** the resulting iteration may have duplicate keys. Use {@see reindex()} to normalize them.
   *
   * @param callable $fn            A callback that receives the current value, key and nesting depth, and returns
   *                                either the value itself or an iterable to replace that value with a sub-iteration.
   *                                To suppress the value from the final iteration, return <kbd>NOIT()</kbd> (the empty
   *                                iterator).
   * @param bool     $keepOriginals Set to TRUE to keep the original values that map to iterables.
   * @return $this
   */
  function recursiveUnfold (callable $fn, $keepOriginals = false)
  {
    $w = function ($v, $k, $d) use ($fn, &$w, $keepOriginals) {
      $r = $fn ($v, $k, $d);
      if (is_iterableEx ($r)) {
        $it = new UnfoldIterator (new MapIterator ($r, $w, $d + 1), UnfoldIterator::USE_ORIGINAL_KEYS);
        return $keepOriginals
          ? new HeadAndTailIterator ($v, $it, $k, true, true)
          : $it;
      }
      return $r;
    };
    $this->map ($w, 0)->unfold ();
    return $this;
  }

  /**
   * Applies a function against an accumulator and each value of the iteration to reduce the iterated data to a single
   * value.
   * This iterator exposes an iteration with a single value: the final result of the reduction.
   *
   * @param callable $fn        Callback to execute for each value of the iteration, taking 3 arguments:
   *                            <dl>
   *                            <dt>$previousValue<dd>The value previously returned in the last invocation of the
   *                            callback, or `$seedValue`, if supplied.
   *                            <dt>$urrentValue <dd>The current element being iterated.
   *                            <dt>$key <dd>The index/key of the current element being iterated.
   *                            </dl>
   * @param mixed    $seedValue Optional value to use as the first argument to the first call of the callback.
   * @return $this
   */
  function reduce (callable $fn, $seedValue = null)
  {
    $this->setIterator (new ReduceIterator ($this->getIterator (), $fn, $seedValue));
    return $this;
  }

  /**
   * Transforms data into arrays of regular expression matches for each item.
   *
   * @param string $regexp     The regular expression to match.
   * @param int    $preg_flags The regular expression flags. Can be a combination of: PREG_PATTERN_ORDER,
   *                           PREG_SET_ORDER, PREG_OFFSET_CAPTURE.
   * @param bool   $useKeys    When `true`, the iterated keys will be used instead of the corresponding values.
   * @return $this
   */
  function regex ($regexp, $preg_flags = 0, $useKeys = false)
  {
    $this->setIterator (
      new RegexIterator ($this->getIterator (), $regexp, RegexIterator::ALL_MATCHES,
        $useKeys ? RegexIterator::USE_KEY : 0,
        $preg_flags)
    );
    return $this;
  }

  /**
   * Transforms data by extracting the first regular expression match for each item.
   *
   * @param string $regexp     The regular expression to match.
   * @param int    $preg_flags The regular expression flags. Can be 0 or PREG_OFFSET_CAPTURE.
   * @param bool   $useKeys    When `true`, the iterated keys will be used instead of the corresponding values.
   * @return $this
   */
  function regexExtract ($regexp, $preg_flags = 0, $useKeys = false)
  {
    $this->setIterator (
      new RegexIterator ($this->getIterator (), $regexp, RegexIterator::GET_MATCH,
        $useKeys ? RegexIterator::USE_KEY : 0,
        $preg_flags)
    );
    return $this;
  }

  /**
   * Transforms each string data item into another using a regular expression.
   *
   * @param string $regexp      The regular expression to match.
   * @param string $replaceWith Literal content with $N placeholders, where N is the capture group index.
   * @param bool   $useKeys     When `true`, the iterated keys will be used instead of the corresponding values.
   * @return $this
   */
  function regexMap ($regexp, $replaceWith, $useKeys = false)
  {
    $this->setIterator (
      new RegexIterator ($this->getIterator (), $regexp, RegexIterator::REPLACE, $useKeys ? RegexIterator::USE_KEY : 0)
    );
    if ($this->it instanceof RegexIterator) {
      if (method_exists ($this->it, 'setReplacement')) {
        $this->it->setReplacement ($replaceWith);
      }
      else {
        $this->it->replacement = $replaceWith;
      }
    }
    return $this;
  }

  /**
   * Splits each data item into arrays of strings using a regular expression.
   *
   * @param string $regexp     The regular expression to match.
   * @param int    $preg_flags The regular expression flags. Can be a combination of: PREG_SPLIT_NO_EMPTY,
   *                           PREG_SPLIT_DELIM_CAPTURE, PREG_SPLIT_OFFSET_CAPTURE.
   * @param bool   $useKeys    When `true`, the iterated keys will be used instead of the corresponding values.
   * @return $this
   */
  function regexSplit ($regexp, $preg_flags = 0, $useKeys = false)
  {
    $this->setIterator (
      new RegexIterator ($this->getIterator (), $regexp, RegexIterator::SPLIT, $useKeys ? RegexIterator::USE_KEY : 0,
        $preg_flags)
    );
    return $this;
  }

  /**
   * Reindexes the current data into a series of sequential integer values, starting from the specified value.
   *
   * @param int $i  The new starting value for the keys sequence.
   * @param int $st The incremental step.
   * @return $this
   */
  function reindex ($i = 0, $st = 1)
  {
    $this->setIterator (new ReindexIterator($this->getIterator (), $i, $st));
    return $this;
  }

  /**
   * Repeats the iteration `$n` times.
   *
   * <p>**Note:** 0 = no iteration, &lt; 0 = infinite
   *
   * <p>**Note:** the resulting iteration may have duplicate keys. Use {@see reindex()} to normalize them.
   *
   * @param int             $n
   * @return $this
   * @property LoopIterator $it
   */
  function repeat ($n)
  {
    $this->setIterator ($it = new LoopIterator ($this->getIterator ()));
    $it->repeat ($n);
    return $this;
  }

  /**
   * Repeats the iteration until the callback returns `false`.
   *
   * <p>**Note:** the resulting iteration may have duplicate keys. Use {@see reindex()} to normalize them.
   *
   * @param callable $fn A callback that receives the current iteration value and key, and returns a boolean.
   * @return $this
   */
  function repeatWhile (callable $fn)
  {
    $this->setIterator ($it = new LoopIterator ($this->getIterator ()));
    $it->test ($fn);
    return $this;
  }

  /**
   * Reverses the order of iteration.
   *
   * Note: this method materializes the data.
   *
   * @param bool $preserveKeys If set to `true` numeric keys are preserved. Non-numeric keys are not affected by this
   *                           setting and will always be preserved.
   * @return $this
   */
  function reverse ($preserveKeys = false)
  {
    $this->pack ();
    $this->it = $this->array_reverse_iterator ($this->data, $preserveKeys);
    unset ($this->data);
    return $this;
  }

  public function rewind (): void
  {
    $this->it->rewind ();
  }

  /**
   * Sets the internal iterator.
   *
   * Not recommended for external use. This is used internally to update the first iterator on the chain.
   *
   * @param mixed $it An iterable.
   * @return $this
   */
  function setIterator ($it)
  {
    $this->it = iterator ($it);
    return $this;
  }

  /**
   * Skips the first `$n` elements from the iteration.
   *
   * @param int $n
   * @return $this
   */
  function skip ($n = 1)
  {
    return $this->slice ($n);
  }

  /**
   * Limits iteration to the specified range.
   *
   * @param int $offset Starts at 0.
   * @param int $count  -1 = all.
   * @return $this
   */
  function slice ($offset = 0, $count = -1)
  {
    $this->setIterator (new LimitIterator ($this->getIterator (), $offset, $count));
    return $this;
  }

  /**
   * Sorts the data by its keys.
   *
   * Note: this method materializes the data.
   *
   * @param string   $type  The type of sort to perform.<br>
   *                        One of:
   *                        'asort' | 'arsort' | 'krsort' | 'ksort' | 'natcasesort' | 'natsort' | 'rsort' | 'shuffle' |
   *                        'sort' | 'uasort' | 'uksort' | 'usort'
   * @param int      $flags One or more of the SORT_XXX constants.
   * @param callable $fn    Can only be specified for sort types beginning with letter `u` (ex: `usort`).
   * @return $this
   */
  function sort ($type = 'sort', $flags = SORT_REGULAR, ?callable $fn = null)
  {
    if (!isset (self::$SORT_TYPES[$type]))
      throw new InvalidArgumentException ("Bad sort type: $type");
    $n = self::$SORT_TYPES[$type];
    $this->all ();  // force materialization of data.
    switch ($n) {
      case 1:
        $type ($this->data);
        break;
      case 2:
        $type ($this->data, $flags);
        break;
      case 3:
        $type ($this->data, $fn);
        break;
    }
    return $this;
  }

  /**
   * Replaces the current data set by another.
   *
   * @param callable $fn A callback that receives as argument an array of the current data and returns the
   *                     new data array.
   * @return $this
   */
  function swap (callable $fn)
  {
    $this->pack ();
    $this->data = $fn ($this->data);
    return $this;
  }

  /**
   * Replaces and expands each iterable value from the current iteration.
   *
   * <p>Each iterable value (a value that is, itself, an iterable) generated by the previous iterator will also be
   * iterated as part of the current iteration.
   * <p>Iterable values are unfolded as they are reached. Non-iterable values are iterated as usual.
   *
   * <p>**Note:** the resulting iteration may have duplicate keys. Use {@see reindex()} to normalize them.
   *
   * @return $this
   */
  function unfold ()
  {
    $this->setIterator (new UnfoldIterator ($this->getIterator (), UnfoldIterator::USE_ORIGINAL_KEYS));
    return $this;
  }

  public function valid (): bool
  {
    return $this->it->valid ();
  }

  /**
   * Filters data by a condition.
   *
   * @param callable $fn A callback that receives the element and its key and returns `true` for the elements that
   *                     should be kept.
   * @return $this
   */
  function where (callable $fn)
  {
    $this->setIterator (new CallbackFilterIterator ($this->getIterator (), $fn));
    return $this;
  }

  /**
   * Filters data using a regular expression test.
   *
   * @param string $regexp     The regular expression to match.
   * @param int    $preg_flags The regular expression flags. Can be 0 or PREG_OFFSET_CAPTURE.
   * @param bool   $useKeys    When `true`, the iterated keys will be used instead of the corresponding values.
   * @return $this
   */
  function whereMatch ($regexp, $preg_flags = 0, $useKeys = false)
  {
    $this->setIterator (new RegexIterator ($this->getIterator (), $regexp, RegexIterator::MATCH,
      $useKeys ? RegexIterator::USE_KEY : 0, $preg_flags));
    return $this;
  }

  /**
   * Continues iterating until the iteration finishes or the callback returns `false` (whichever occurs first).
   *
   * @param callable $fn A callback that receives the current iteration value and key, and returns a boolean.
   * @return $this
   */
  function while_ (callable $fn)
  {
    $this->setIterator ($it = new ConditionalIterator ($this->getIterator (), $fn));
    return $this;
  }

  /**
   * Returns an iterator that iterates an array on reverse.
   *
   * @param array $a
   * @param bool  $preserveKeys If set to `true` numeric keys are preserved. Non-numeric keys are not affected by this
   *                            setting and will always be preserved.
   * @return \Generator
   */
  private function array_reverse_iterator (array $a, $preserveKeys = false)
  {
    if ($preserveKeys)
      for (end ($a); ($key = key ($a)) !== null; prev ($a))
        yield $key => current ($a);
    else for (end ($a); ($key = key ($a)) !== null; prev ($a))
      yield current ($a);
  }

}
