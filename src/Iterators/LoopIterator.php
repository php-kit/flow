<?php
namespace Flow\Iterators;
use IteratorIterator;

/**
 * Allows the looping of another iterator, with some constraints.
 *
 * One can limit the total amount of iterations (the outer iterator will loop as many times as needed)
 * or one can limit how many times the outer iterator is looped.
 * If both counts are specified, the iteration will stop with whichever ends first.
 *
 * After calling the constructor, you should call either {@see LoopIterator::loop()} or {@see LoopIterator::limit()} to
 * set a limit, otherwise the iterator will loop only once.
 */
class LoopIterator extends IteratorIterator
{
  private $limit = -1;
  private $times = 1;

  /**
   * Limit the total amount of iterations to perform, irrespective of loops.
   * <p>Note: 0 = no iteration, &lt; 0 = infinite
   * <p>If the limit is greater than the iterator's count, the iteration will stop when the iterator is exhausted,
   * unless you also set {@see LoopIterator::loop()}.
   * @param int $total
   */
  function limit ($total)
  {
    $this->limit = $total;
  }

  /**
   * Number of times the original iterator should be repeated.
   * <p>Note: 0 = none, &lt; 0 = forever
   * @param int $times
   */
  function loop ($times)
  {
    $this->times = $times;
  }

  public function next ()
  {
    parent::next ();
    if ($this->limit && $this->times) {
      if ($this->limit) --$this->limit;
    }
  }

  public function valid ()
  {
    if ($this->limit && $this->times) {
      $v = parent::valid ();
      if (!$v) {                // if data exhausted
        if (!--$this->times)    // decrease loops remaining
          return false;         // terminate if loops exhausted
        $this->rewind ();        // otherwise, start next iteration loop
      }
      return true;
    }
    return false;
  }
}
