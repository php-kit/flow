<?php
namespace Flow\Iterators;
use Flow\Flow;
use Iterator;
use OuterIterator;

/**
 * Replaces and expands each iterated value of another iterator.
 */
class MacroIterator implements OuterIterator
{
  /**
   * When this options is set, the resulting iteration preserves the original keys from each successive inner iterator.
   * When not set, keys are auto-incremented integers starting at 0.
   */
  const USE_ORIGINAL_KEYS = 1;
  /** @var int */
  private $flags;
  /** @var callable */
  private $fn;
  /** @var int */
  private $index;
  /** @var Iterator */
  private $inner;
  /** @var Iterator */
  private $outer;

  /**
   * @param mixed    $iterator An iterable.
   * @param callable $fn       A callback that receives the current outer iterator item's value and key
   *                           and returns the corresponding inner Traversable or array.
   * @param int      $flags    Iterator One of the self::XXX constants.
   */
  function __construct ($iterator, callable $fn, $flags = 0)
  {
    $this->inner = Flow::iteratorFrom ($iterator);
    $this->fn    = $fn;
    $this->flags = $flags;
  }

  function current ()
  {
    return $this->outer->current ();
  }

  function getInnerIterator ()
  {
    return $this->inner;
  }

  function key ()
  {
    return $this->flags & self::USE_ORIGINAL_KEYS ? $this->outer->key () : $this->index;
  }

  function next ()
  {
    ++$this->index;
    $this->outer->next ();
    if (!$this->outer->valid ()) {
      $this->inner->next ();
      $this->nextOuter ();
    }
  }

  function rewind ()
  {
    $this->index = 0;
    $this->inner->rewind ();
    $this->nextOuter ();
  }

  function valid ()
  {
    return isset($this->outer);
  }

  /**
   * Advance the inner iterator until we get a non-empty outer iterator.
   */
  function nextOuter ()
  {
    while ($this->inner->valid ()) {
      $fn          = $this->fn;
      $this->outer = Flow::iteratorFrom ($fn ($this->inner->current (), $this->inner->key ()));
      $this->outer->rewind ();
      if ($this->outer->valid ()) return;
      $this->inner->next ();
    }
    $this->outer = null;
  }

}
