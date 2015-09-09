<?php
namespace PhpKit\Flow\Iterators;
use IteratorIterator;
use Traversable;

/**
 * Iterates another iterator until the iteration finishes or the given callback returns `false` (whichever occurs
 * first).
 */
class ConditionalIterator extends IteratorIterator
{
  /** @var callable */
  private $test;

  /**
   * @param Traversable $iterator The source iterator.
   * @param callable    $test     A callback that receives the current iteration value and key, and returns a boolean.
   *                              If it returns `true` the iteration continues, otherwise the iteration stops and no
   *                              further calls to the callback will be mad until the iterator is rewound.
   *                              <p>Note: the callback is only called while the inner iterator is valid.
   */
  public function __construct (Traversable $iterator, callable $test)
  {
    parent::__construct ($iterator);
    $this->test = $test;
  }

  public function valid ()
  {
    $v = parent::valid ();
    if ($v) {
      $fn = $this->test;
      return $fn ($this->current (), $this->key ());
    }
    return false;
  }

}
