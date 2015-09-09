<?php
namespace PhpKit\Flow\Iterators;
use Iterator;

/**
 * Provides one single iteration of a constant value.
 */
class SingleValueIterator implements Iterator
{
  private $read;
  private $v;

  /**
   * @param mixed $value Any type of value.
   */
  function __construct ($value)
  {
    $this->v = $value;
  }

  public function current ()
  {
    return $this->v;
  }

  public function key ()
  {
    return $this->read ? 1 : 0;
  }

  public function next ()
  {
    $this->read = true;
  }

  public function rewind ()
  {
    $this->read = false;
  }

  public function valid ()
  {
    return !$this->read;
  }

}
