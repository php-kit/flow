<?php
namespace PhpKit\Flow\Iterators;
use IteratorIterator;
use Traversable;

/**
 * Transforms data from another iterator using a callback function.
 */
class MapIterator extends IteratorIterator
{
  /** @var mixed */
  private $arg;
  /** @var mixed */
  private $cur;
  /** @var callable */
  private $fn;
  /** @var mixed */
  private $key;

  /**
   * @param Traversable $iterator The source iterator.
   * @param callable    $mapFn    A callback that receives a value and a key and returns the new value.<br>
   *                              It can also receive the key by reference and change it.
   *                              <p>Ex:
   *                              <code>  new MapIterator ($iter, function ($v, &$k) { $k=$k*10; return $v*$v;})</code>
   * @param mixed       $arg      An optional extra argument to be passed to the callback on every iteration.
   *                              <p>The callback can change the argument if it declares the parameter as a reference.
   */
  function __construct (Traversable $iterator, callable $mapFn, $arg = null)
  {
    parent::__construct ($iterator);
    $this->fn  = $mapFn;
    $this->arg = $arg;
  }

  function current (): mixed
  {
    return $this->cur;
  }

  function key (): mixed
  {
    return $this->key;
  }

  public function valid (): bool
  {
    $v = parent::valid ();
    if ($v) {
      $fn        = $this->fn;
      $this->key = parent::key ();
      $this->cur = $fn (parent::current (), $this->key, $this->arg);
    }
    else $this->key = $this->cur = null; // release resources now
    return $v;
  }
}
