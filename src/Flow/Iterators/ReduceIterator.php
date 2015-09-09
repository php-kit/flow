<?php
namespace PhpKit\Flow\Iterators;
use Iterator;
use Traversable;

/**
 * Applies a function against an accumulator and each value from a given iterator to reduce the iterated data to a
 * single value.
 *
 * This iterator exposes an iteration with a single value: the final result of the reduction.
 */
class ReduceIterator implements Iterator
{
  /** @var callable */
  private $fn;
  private $idx;
  /** @var Iterator */
  private $it;
  private $result;
  private $seed;

  /**
   * @param Traversable $iterator  The source iterator.
   * @param callable    $fn        Callback to execute for each value of the iteration, taking 3 arguments:
   *                               <dl>
   *                               <dt>$previousValue<dd>The value previously returned in the last invocation of the
   *                               callback, or `$seedValue`, if supplied.
   *                               <dt>$urrentValue <dd>The current element being iterated.
   *                               <dt>$key <dd>The index/key of the current element being iterated.
   *                               </dl>
   * @param mixed       $seedValue Optional value to use as the first argument to the first call of the callback.
   */
  public function __construct (Traversable $iterator, callable $fn, $seedValue = null)
  {
    $this->it   = $iterator;
    $this->fn   = $fn;
    $this->seed = $seedValue;
  }

  public function current ()
  {
    return $this->result;
  }

  public function key ()
  {
    return $this->idx;
  }

  public function next ()
  {
    ++$this->idx;
  }

  public function rewind ()
  {
    $this->idx = 0;
    $prev      = $this->seed;
    $it        = $this->it;
    $it->rewind ();
    $fn = $this->fn;
    while ($it->valid ()) {
      $prev = $fn ($prev, $it->current (), $it->key ());
      $it->next ();
    }
    $this->result = $prev;
  }

  public function valid ()
  {
    return !$this->idx;
  }

}
