<?php
namespace PhpKit\Flow\Iterators;

use Iterator;

/**
 * Provides one single iteration of a constant value.
 */
class SingleValueIterator implements Iterator
{

	private $read;
	private $v;

	/**
	 * @param mixed $value Any type of value.
	 */
	function __construct($value)
	{
		$this->v = $value;
	}

	public function current(): mixed
	{
		return $this->v;
	}

	public function key(): mixed
	{
		return $this->read ? 1 : 0;
	}

	public function next(): void
	{
		$this->read = true;
	}

	public function rewind(): void
	{
		$this->read = false;
	}

	public function valid(): bool
	{
		return !$this->read;
	}

}
