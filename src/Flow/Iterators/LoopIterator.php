<?php
namespace PhpKit\Flow\Iterators;

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
	private $loops = 1;

	/** @var callable */
	private $test;

	/**
	 * Limit the total amount of iterations to perform, irrespective of loops.
	 * <p>Note: 0 = no iteration, &lt; 0 = infinite
	 * <p>If the limit is greater than the iterator's count, the iteration will stop when the iterator is exhausted,
	 * unless you also set {@see LoopIterator::loop()}.
	 * @param int $total
	 * @return $this
	 */
	function limit($total)
	{
		$this->limit = $total;
		return $this;
	}

	public function next(): void
	{
		parent::next();
		if ($this->limit && $this->loops)
		{
			if ($this->limit)
				--$this->limit;
		}
	}

	public function valid(): bool
	{
		if ($this->limit && $this->loops)
		{
			$v = parent::valid();
			if (!$v)
			{	// if data exhausted
				// if using a callback, let it decide if one loops or not
				$fn = $this->test;
				if (isset($fn))
					return $fn($this->current(), $this->key());

				// otherwise, use the loop limit counter
				if (!--$this->loops) // decrease loops remaining
					return false;   // terminate if loops exhausted
				$this->rewind();  // otherwise, start next iteration loop
			}
			return true;
		}
		return false;
	}

	/**
	 * Number of times the original iterator should be repeated.
	 * <p>Note: 0 = none, &lt; 0 = forever
	 * @param int $times
	 * @return $this
	 */
	function repeat($times)
	{
		$this->loops = $times;
		return $this;
	}

	/**
	 * Repeats the iteration until the callback returns `false`.
	 * @param callable $fn      A callback that receives the current iteration value and key, and returns a boolean.
	 *                          If it returns `true` another iteration loop begins, otherwise the iteration stops and no
	 *                          further calls to the callback will be mad until the iterator is rewound.
	 * @return $this
	 */
	function test(callable $fn)
	{
		$this->test = $fn;
		return $this;
	}

}
