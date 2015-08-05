<?php
namespace Flow\Iterators;
use Flow\Flow;
use IteratorIterator;
use RecursiveIterator as RecursiveIteratorInterface;

/**
 * A generic recursive iterator that defines the recursion via a user-defined callback function.
 */
class RecursiveIterator extends IteratorIterator implements RecursiveIteratorInterface
{
  private $children;
  private $depth;
  private $fn;

  /**
   * (PHP 5 &gt;= 5.1.0)<br/>
   * Creates a recursive iterator from anything that is traversable.
   * @link http://php.net/manual/en/iteratoriterator.construct.php
   * @param mixed    $iterator A Traversable, array or function.
   * @param callable $fn       A callback that receives the current node's value, key and nesting depth, and returns an
   *                           array or {@see Traversable} for the node's children or `null` if the node has no
   *                           children.
   * @param int      $depth    The current nesting depth. You don't need to manually specify this argument.
   */
  public function __construct ($iterator, callable $fn, $depth = 0)
  {
    parent::__construct (Flow::iteratorFrom ($iterator));
    $this->fn    = $fn;
    $this->depth = $depth;
  }

  public function getChildren ()
  {
    return $this->children;
  }

  public function hasChildren ()
  {
    $fn = $this->fn;
    $r  = $fn ($this->current (), $this->key (), $this->depth);
    if (is_null ($r)) {
      $this->children = null;
      return false;
    }
    $this->children =
      new \RecursiveIteratorIterator(
        new RecursiveIterator(
          Flow::iteratorFrom ($r), $fn, $this->depth + 1
        )
      );
    return true;
  }
}
