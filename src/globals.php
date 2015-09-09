<?php
use PhpKit\Flow\Flow;
use PhpKit\Flow\Iterators\FunctionIterator;
use PhpKit\Flow\Iterators\SingleValueIterator;

/**
 * Creates a Flow iteration from any value.
 *
 * If the value is not iterable, an iterator for a single value is returned.
 * @param mixed $stuff Anything.
 * @return Flow
 */
function flow ($stuff)
{
  return Flow::from ($stuff);
}

function is_traversable ($x)
{
  return $x instanceof Traversable || is_array ($x);
}

function is_iterable ($x)
{
  return $x instanceof Traversable || is_array ($x) || is_callable ($x);
}

/**
 * Converts the argument into an iterator.
 * @param mixed $t Any value type. If it is not iterable, an iterator for that single value is returned.
 * @return Iterator
 */
function iterator ($t)
{
  switch (true) {
    case $t instanceof IteratorAggregate:
      return $t->getIterator ();
    case $t instanceof Iterator:
      return $t;
    case is_array ($t):
      return new ArrayIterator ($t);
    case is_callable ($t):
      return new FunctionIterator ($t);
    default:
      return new SingleValueIterator ($t);
  }
}

function NOIT ()
{
  static $it;
  if (!isset($it)) $it = new EmptyIterator;
  return $it;
}
