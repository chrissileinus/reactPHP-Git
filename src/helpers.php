<?php

namespace Chrissileinus\React\Git;

class helpers
{
  /**
   * Is path absolute?
   * Method from Nette\Utils\FileSystem
   * @link   https://github.com/nette/nette/blob/master/Nette/Utils/FileSystem.php
   * @param  string $path
   * @return bool
   */
  public static function isAbsolute($path)
  {
    return (bool) preg_match('#[/\\\\]|[a-zA-Z]:[/\\\\]|[a-z][a-z0-9+.-]*://#Ai', $path);
  }

  public static function forceToUTF8(string $string)
  {
    if (forceUTF8 && !\mb_detect_encoding($string, 'UTF-8', true)) return utf8_encode($string);
    return $string;
  }
}
