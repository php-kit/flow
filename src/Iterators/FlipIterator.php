<?php
namespace Flow\Iterators;
use IteratorIterator;
use Traversable;

/**
 * Iterates a given iterator swapping keys for values and/or vice-versa.
 */
class FlipIterator extends IteratorIterator
{
  private $fk;
  private $fv;

  public function __construct (Traversable $iterator, $flipValues = true, $flipKeys = true)
  {
    parent::__construct ($iterator);
    $this->fv = $flipValues;
    $this->fk = $flipKeys;
  }

  function current ()
  {
    return $this->fv ? parent::key () : parent::current ();
  }

  function key ()
  {
    return $this->fk ? parent::current () : parent::key ();
  }

}
