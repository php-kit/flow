<?php
namespace Flow;
use FilesystemIterator;
use GlobIterator;
use SplFileInfo;

class FilesystemFlow extends Flow
{
  /**
   * Creates a filesystem directory query.
   * @param string $path  The directory path.
   * @param int    $flags One of the FilesystemIterator::XXX flags.<br>
   *                      Default = KEY_AS_PATHNAME | CURRENT_AS_FILEINFO | SKIP_DOTS
   * @return static
   */
  static function from ($path, $flags = 4096)
  {
    return new static (new FilesystemIterator($path, $flags));
  }

  /**
   * Iterates through a file system in a similar fashion to {@see glob()}.
   * @param string $path  The directory path and pattern. No tilde expansion or parameter substitution is done.
   * @param int    $flags One of the FilesystemIterator::XXX flags.<br>
   *                      Default = KEY_AS_PATHNAME | CURRENT_AS_FILEINFO
   * @return static
   */
  static function glob ($path, $flags = 0)
  {
    return new static (new GlobIterator($path, $flags));
  }

  function onlyDirectories ()
  {
    return $this->where (function (SplFileInfo $f) { return $f->isDir (); });
  }

  function onlyFiles ()
  {
    return $this->where (function (SplFileInfo $f) { return $f->isFile (); });
  }

}
