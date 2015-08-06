<?php
namespace Flow\Iterators;
use Flow\Flow;
use Iterator;
use OuterIterator;
use Traversable;

/**
 * An OuterIterator implementation that allows the caller to define an inner iterator to replace and expand each item
 * of the outer iterator.
 */
class MacroIterator implements OuterIterator
{
  /**
   * When this options is set, the resulting iteration preserves the original keys from each successive inner interator.
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
   * @param Traversable|array|callable $outer The outer iterator.
   * @param callable                   $fn    A callback that receives the current outer iterator item's value and key
   *                                          and returns the corresponding inner Traversable or array.
   * @param int                        $flags Iterator One of the self::XXX constants.
   */
  function __construct ($outer, callable $fn, $flags = 0)
  {
    $this->outer = Flow::iteratorFrom ($outer);
    $this->fn    = $fn;
    $this->flags = $flags;
  }

  function current ()
  {
    return $this->inner->current ();
  }

  function getInnerIterator ()
  {
    return $this->inner;
  }

  function key ()
  {
    return $this->flags & self::USE_ORIGINAL_KEYS ? $this->inner->key () : $this->index;
  }

  function next ()
  {
    ++$this->index;
    $this->inner->next ();
    while (!$this->inner->valid ()) {
      $this->outer->next ();
      if (!$this->outer->valid ()) {
        $this->inner = null;
        return;
      }
      $this->nextInner ();
    }
  }

  function rewind ()
  {
    $this->index = 0;
    $this->inner = null;
    $this->outer->rewind ();
    while ($this->outer->valid ()) {
      $this->nextInner ();
      $this->inner->rewind ();
      if ($this->inner->valid ()) return;
      $this->outer->next ();
    }
  }

  function valid ()
  {
    return $this->inner && $this->inner->valid ();
  }

  protected function nextInner ()
  {
    $fn          = $this->fn;
    $this->inner = Flow::iteratorFrom ($fn ($this->outer->current (), $this->outer->key ()));
  }
}
