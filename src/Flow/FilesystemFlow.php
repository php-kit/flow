<?php
namespace PhpKit\Flow;

use FilesystemIterator;
use GlobIterator;
use SplFileInfo;

class FilesystemFlow extends Flow
{
  const DIRECTORIES_FIRST = 1;
  const FILES_FIRST       = 2;
  const FILES_ONLY        = 0;

  /**
   * Creates a filesystem directory query.
   *
   * @param string $path  The directory path.
   * @param int    $flags One of the {@see FilesystemIterator} flags.<br>
   *                      Default = KEY_AS_PATHNAME | CURRENT_AS_FILEINFO | SKIP_DOTS
   * @return static
   */
  static function from ($path, $flags = 4096)
  {
    try {
      return new static (new FilesystemIterator($path, $flags));
    }
    catch (\Exception $e) {
      // Convert UnexpectedValueException to another type to prevent problems with some error handlers.
      throw new \InvalidArgumentException($e->getMessage ());
    }
  }

  /**
   * Iterates through a file system directory in a similar fashion to {@see glob()}.
   *
   * @param string $path  The directory path and pattern. No tilde expansion or parameter substitution is done.
   * @param int    $flags One of the FilesystemIterator::XXX flags.<br>
   *                      Default = KEY_AS_PATHNAME | CURRENT_AS_FILEINFO
   * @return static
   */
  static function glob ($path, $flags = 0)
  {
    return new static (new GlobIterator($path, $flags));
  }

  /**
   * Creates a recursive filesystem directory query.
   *
   * <p>By default, the query returns all files and directories on the specified directory recursively for each
   * subdirectory found.
   *
   * <p>Note: instead of setting the `$mode` argument, you may use instead the {@see onlyDirectories()} and
   * {@see onlyFiles()} filters.
   *
   * @param string $path  The directory path.
   * @param int    $flags One of the {@see FilesystemIterator} flags.<br>
   *                      Default = KEY_AS_PATHNAME | CURRENT_AS_FILEINFO | SKIP_DOTS
   * @param int    $mode  One of these constants from {@see FilesystemFlow}:
   *                      <p> 0 = FILES_ONLY
   *                      <p> 1 = DIRECTORIES_FIRST (default)
   *                      <p> 2 = FILES_FIRST
   *                      <p>Note: these constants mirror those from {@see RecursiveIteratorIterator}
   * @return static
   */
  static function recursiveFrom ($path, $flags = 4096, $mode = self::DIRECTORIES_FIRST)
  {
    try {
      return (new static (new FilesystemIterator($path, $flags)))->recursive (function ($v, $k, $d) use ($flags) {
        $p = is_string ($v) ? $v : ($v instanceof SplFileInfo ? $v->getPathname () : $k);
        $r = is_dir ($p) ? new FilesystemIterator ($p, $flags) : null;
        return $r;
      }, $mode);
    }
    catch (\Exception $e) {
      // Convert UnexpectedValueException to another type to prevent problems with some error handlers.
      throw new \InvalidArgumentException($e->getMessage ());
    }
  }

  /**
   * Iterates through a file system directory and all of its subdirectories in a similar fashion to {@see glob()}.
   *
   * @param string $rootDir The directory path from where to start searching.
   * @param string $pattern The file matching glob pattern. No tilde expansion or parameter substitution is done.
   * @param int    $flags   One of the FilesystemIterator::XXX flags, for use by a <kbd>GlobIterator</kbd><br>
   *                        Default = KEY_AS_PATHNAME | CURRENT_AS_FILEINFO
   * @return static
   */
  static function recursiveGlob ($rootDir, $pattern, $flags = 0)
  {
    $k = $flags && FilesystemIterator::KEY_AS_FILENAME ? basename ($rootDir) : $rootDir;
    $v = $flags && FilesystemIterator::CURRENT_AS_PATHNAME ? $rootDir : new SplFileInfo ($rootDir);

    return static::recursiveFrom ($rootDir)->onlyDirectories ()->prependValue ($v, $k)->expand (
      function (SplFileInfo $finfo, $path) use ($pattern, $flags) {
        return new GlobIterator("$path/$pattern", $flags);
      }, false);
  }

  /**
   * Restricts the iteration to directories only.
   *
   * @return $this
   */
  function onlyDirectories ()
  {
    return $this->where (function ($f) {
      if (!is_object ($f) || !$f instanceof SplFileInfo)
        throw new \RuntimeException ("You must use FilesystemIterator::CURRENT_AS_PATHNAME with onlyDirectories() ");
      return $f->isDir ();
    });
  }

  /**
   * Restricts the iteration to files only.
   *
   * @return $this
   */
  function onlyFiles ()
  {
    return $this->where (function ($f) {
      if (!is_object ($f) || !$f instanceof SplFileInfo)
        throw new \RuntimeException ("You must use FilesystemIterator::CURRENT_AS_PATHNAME with onlyFiles()");
      return $f->isFile ();
    });
  }

}
