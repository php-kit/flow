# Flow
##### Iterator Nirvana for PHP

#### Runtime requirements

- PHP >= 5.4

## Introduction

Flow provides a fluent interface to assemble chains of iterators and other data processing operations.

The fluent API makes it **very easy and intuitive** to use the native SPL iterators (together with some custom
ones provided by this library) and the expressive syntax allows you to assemble sophisticated processing pipelines
in an elegant, terse and readable fashion.

An iteration chain assembled with this builder can perform multiple transformations over a data flow without
storing in memory the resulting data from intermediate steps. When operating over large data sets, this mechanism
can be very light on memory consumption.

Inputs to the chain can be any kind of `Traversable`, i.e. native arrays, classes implementing
`Iterator` or `IteratorAggregate`, or the result of invoking a generator function (on PHP>=5.5).
Additionaly, callable inputs (ex. Closures) will be converted to `FunctionIterator` instances, allowing you to
write generator function look-alikes on PHP<5.5.

> Some operations require the iteration data to be "materialized", i.e. fully iterated and stored internally
as an array, before the operation is applied. This only happens for operations that require all data to be present
(ex: `reverse()` or `sort()`), and the resulting data will be automatically converted back to an iterator whenever it
makes sense.


## License

This library is open-source software licensed under the [MIT license](http://opensource.org/licenses/MIT).

**Flow** - Copyright &copy; 2015 Impactwave, Lda.
