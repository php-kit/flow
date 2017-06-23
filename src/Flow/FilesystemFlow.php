<?php
namespace PhpKit\Flow;

use FilesystemIterator;
use GlobIterator;
use SplFileInfo;

class FilesystemFlow extends Flow
{
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
   * Creates a filesystem directory query.
   *
   * @param string $path  The directory path.
   * @param int    $flags One of the {@see FilesystemIterator} flags.<br>
   *                      Default = KEY_AS_PATHNAME | CURRENT_AS_FILEINFO | SKIP_DOTS
   * @return static
   */
  static function recursiveFrom ($path, $flags = 4096)
  {
    try {
      return (new static (new FilesystemIterator($path, $flags)))->recursiveUnfold (function ($v, $k, $d) use ($flags) {
        $p = is_string ($v) ? $v : ($v instanceof SplFileInfo ? $v->getPathname () : $k);
        $r = is_dir ($p) ? new FilesystemIterator ($p, $flags) : $v;
        return $r;
      }, true);
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

  function onlyDirectories ()
  {
    return $this->where (function ($f) {
      if (!is_object ($f) || !$f instanceof SplFileInfo)
        throw new \RuntimeException ("You must use FilesystemIterator::CURRENT_AS_PATHNAME with onlyDirectories() ");
      return $f->isDir ();
    });
  }

  function onlyFiles ()
  {
    return $this->where (function ($f) {
      if (!is_object ($f) || !$f instanceof SplFileInfo)
        throw new \RuntimeException ("You must use FilesystemIterator::CURRENT_AS_PATHNAME with onlyFiles()");
      return $f->isFile ();
    });
  }

}
