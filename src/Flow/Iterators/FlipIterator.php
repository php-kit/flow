<?php
namespace PhpKit\Flow\Iterators;
use IteratorIterator;
use Traversable;

/**
 * Iterates a given iterator swapping keys for values and/or vice-versa.
 */
class FlipIterator extends IteratorIterator
{
  private $fk;
  private $fv;

  /**
   * @param Traversable $iterator   The source iterator.
   * @param bool|true   $flipValues Output keys instead of values?
   * @param bool|true   $flipKeys   Output values instead of keys?z
   */
  public function __construct (Traversable $iterator, $flipValues = true, $flipKeys = true)
  {
    parent::__construct ($iterator);
    $this->fv = $flipValues;
    $this->fk = $flipKeys;
  }

  function current (): mixed
  {
    return $this->fv ? parent::key () : parent::current ();
  }

  function key (): mixed
  {
    return $this->fk ? parent::current () : parent::key ();
  }

}
