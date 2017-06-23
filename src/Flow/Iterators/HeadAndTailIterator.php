<?php
namespace PhpKit\Flow\Iterators;

use Iterator;

/**
 * Provides one single iteration of a constant value.
 */
class HeadAndTailIterator implements Iterator
{
  private $head;
  private $headKey;
  private $idx = 0;
  /**
   * @var bool
   */
  private $keepTailKeys;
  private $tail;

  /**
   * An iterator for a list comprised of a head value followed by a tail iteration that defines the remaining elements.
   *
   * @param mixed $head         The first value of the iteration.
   * @param mixed $tail         An iterable sequence.
   * @param mixed $headKey      The first key of the iteration.
   * @param bool  $keepTailKeys TRUE to return the original tail keys, FALSE to reindex them.
   */
  function __construct ($head, $tail, $headKey = 0, $keepTailKeys = false)
  {
    $this->head         = $head;
    $this->tail         = iterator ($tail);
    $this->headKey = $headKey;
    $this->keepTailKeys = $keepTailKeys;
  }

  public function current ()
  {
    return $this->idx ? $this->tail->current () : $this->head;
  }

  public function key ()
  {
    return $this->idx ? ($this->keepTailKeys ? $this->tail->key () : $this->idx) : $this->headKey;
  }

  public function next ()
  {
    if (++$this->idx > 1)
      $this->tail->next ();
  }

  public function rewind ()
  {
    $this->idx = 0;
    $this->tail->rewind ();
  }

  public function valid ()
  {
    return !$this->idx || $this->tail->valid ();
  }

}
