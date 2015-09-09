<?php
namespace PhpKit\Flow\Iterators;
use Iterator;
use OuterIterator;

/**
 * Replaces and expands each iterated value of another iterator.
 *
 * <p>Each iterable value (a value that is, itself, an iterable) generated by the previous iterator will also be
 * iterated as part of the current iteration.
 * <p>Iterable values are unfolded as they are reached. Non-iterable values are iterated as usual.
 */
class UnfoldIterator implements OuterIterator
{
  /**
   * When this options is set, the resulting iteration preserves the original keys from each successive inner iterator.
   * When not set, keys are auto-incremented integers starting at 0.
   */
  const USE_ORIGINAL_KEYS = 1;
  /** @var int */
  private $flags;
  /** @var int */
  private $index;
  /** @var Iterator */
  private $inner;
  /** @var Iterator */
  private $outer;

  /**
   * @param mixed $iterator An iterable.
   * @param int   $flags    Iterator One of the self::XXX constants.
   */
  function __construct ($iterator, $flags = 0)
  {
    $this->inner = iterator ($iterator);
    $this->flags = $flags;
  }

  function current ()
  {
    return isset($this->outer) ? $this->outer->current () : $this->inner->current ();
  }

  function getInnerIterator ()
  {
    return $this->inner;
  }

  function key ()
  {
    return $this->flags & self::USE_ORIGINAL_KEYS
      ? (isset($this->outer) ? $this->outer->key () : $this->inner->key ())
      : $this->index;
  }

  function next ()
  {
    ++$this->index;
    if (isset($this->outer)) {
      $this->outer->next ();
      if ($this->outer->valid ()) return;
    }
    $this->inner->next ();
    $this->nextOuter ();
  }

  function rewind ()
  {
    $this->index = 0;
    $this->inner->rewind ();
    $this->nextOuter ();
  }

  function valid ()
  {
    return isset($this->outer) || $this->inner->valid ();
  }

  /**
   * Advance the inner iterator until we get a non-empty outer iterator.
   */
  function nextOuter ()
  {
    while ($this->inner->valid ()) {
      $v = $this->inner->current ();
      if (is_iterable ($v)) {
        $this->outer = iterator ($v);
        $this->outer->rewind ();
        if ($this->outer->valid ()) return;
        $this->inner->next ();
      }
      else break;
    }
    $this->outer = null;
  }

}
