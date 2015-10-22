<?php
namespace PhpKit\Flow\Iterators;
use IteratorIterator;
use Traversable;

/**
 * Iterates another iterator replacing keys by a generated sequence of numbers.
 */
class ReindexIterator extends IteratorIterator
{
  private $from;
  private $idx;
  private $step;

  /**
   * @param Traversable $it   The source iterator.
   * @param int|float   $from Starting value.
   * @param int|float   $step Can be either positive or negative. If zero, an infinite sequence of constant values is
   *                          generated.
   */
  function __construct (Traversable $it, $from, $step = 1)
  {
    parent::__construct ($it);
    $this->idx  = $this->from = $from;
    $this->step = $step;
  }

  public function key ()
  {
    return $this->idx;
  }

  public function next ()
  {
    parent::next ();
    $this->idx += $this->step;
  }

  public function rewind ()
  {
    parent::rewind ();
    $this->idx = $this->from;
  }

}
