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

function iterable_to_array ($x, $use_keys = true)
{
  return iterator_to_array (iterator ($x), $use_keys);
}

/**
 * Merges an iterable sequence to the target array, modifying the original.
 * @param array $a
 * @param mixed $it
 * @param bool  $use_keys
 */
function array_mergeIterable (array &$a, $it, $use_keys = true)
{
  $a = array_merge ($a, iterable_to_array ($it, $use_keys));
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
      return iterator ($t->getIterator ());
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
