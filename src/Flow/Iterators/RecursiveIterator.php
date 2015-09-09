<?php
namespace PhpKit\Flow\Iterators;
use IteratorIterator;
use RecursiveIterator as RecursiveIteratorInterface;
use Traversable;

/**
 * A generic recursive iterator that defines the recursion via a user-defined callback function.
 */
class RecursiveIterator extends IteratorIterator implements RecursiveIteratorInterface
{
  private $children;
  private $depth;
  private $fn;

  /**
   * @param Traversable $iterator The source iterator.
   * @param callable    $fn       A callback that receives the current node's value, key and nesting depth, and returns
   *                              an array or {@see Traversable} for the node's children or `null` if the node has no
   *                              children.
   * @param int         $depth    The initial/current nesting depth. You don't need to specify this argument, it will be
   *                              automatically auto-incremented from 0.
   */
  public function __construct (Traversable $iterator, callable $fn, $depth = 0)
  {
    parent::__construct ($iterator);
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
    $this->children = new RecursiveIterator(iterator ($r), $fn, $this->depth + 1);
    return true;
  }
}
