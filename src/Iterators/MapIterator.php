<?php
namespace Flow\Iterators;
use IteratorIterator;

/**
 * Transforms the iterated data using a callback function.
 */
class MapIterator extends IteratorIterator
{
  /** @var mixed */
  private $cur;
  /** @var callable */
  private $fn;
  /** @var mixed */
  private $key;

  /**
   * @param \Traversable $iterator The source data.
   * @param callable     $mapFn    A callback that receives a value and a key and returns the new value.<br>
   *                               It can also receive the key by reference and change it.
   *                               <p>Ex:
   *                               <code>  new MapIterator ($iter, function ($v, &$k) { $k = $k * 10; return $v * 100;
   *                               })</code>
   */
  function __construct (\Traversable $iterator, callable $mapFn)
  {
    parent::__construct ($iterator);
    $this->fn = $mapFn;
  }

  function current ()
  {
    return $this->cur;
  }

  function key ()
  {
    return $this->key;
  }

  public function valid ()
  {
    $v = parent::valid ();
    if ($v) {
      $fn        = $this->fn;
      $this->key = parent::key ();
      $this->cur = $fn (parent::current (), $this->key);
    }
    return $v;
  }
}
