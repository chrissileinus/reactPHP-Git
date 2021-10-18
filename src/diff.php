<?php
namespace Chrissileinus\React\Git;

class diff {
  static string $regex = '/^^diff --git \S(?<fileA>.+) \S(?<fileB>.+)\n(?:|(?:(?<type>\S+) .*\n))index (?<blobA>\S+)\.\.(?<blobB>\S+)(?: \S+|)\n(?:.+\n.+\n(?<parts>(?:[@ +-].*\n)+)|)/m';

  public string $fileA;
  public string $fileB;
  public string $blobA;
  public string $blobB;
  public string $type;
  public array $parts;

  function __construct (string $string) {
    if (preg_match_all(self::$regex, $string, $matches))
    foreach ($matches[0] as $key => $value) {
      $this->fileA  = $matches['fileA'][$key];
      $this->fileB  = $matches['fileB'][$key];
      $this->blobA  = $matches['blobA'][$key];
      $this->blobB  = $matches['blobB'][$key];
      $this->type   = $matches['type'][$key] == ''? 'modified': $matches['type'][$key];
      if (preg_match_all(part::$regex, trim($matches['parts'][$key]), $matches))
      foreach ($matches[0] as $value) {
        $this->parts[] = new part($value);
      }
    }
  }
}

class part {
  static string $regex = '/^@@ \-(?<partA>.+) \+(?<partB>.+) @@(?<content>(?:(?!@@ \-.+ \+.+ @@).*\n)+)/m';

  public partSector $A;
  public partSector $B;
  public string $content;

  function __construct (string $string) {
    if (preg_match_all(self::$regex, $string, $matches))
    foreach ($matches[0] as $key => $value) {
      $this->A        = new partSector($matches['partA'][$key]);
      $this->B        = new partSector($matches['partB'][$key]);
      $this->content  = helpers::forceToUTF8(trim($matches['content'][$key], "\t\n\r\0\x0B"));
    }
  }
}

class partSector {
  public int $start;
  public int $length;

  function __construct (string $part) {
    if (preg_match("/(?<start>\d+),(?<length>\d+)/", $part, $match)) {
      $this->start  = $match['start'];
      $this->length = $match['length'];
      return;
    }
    if (preg_match("/(?<start>\d+)/", $part, $match)) {
      $this->start  = $match['start'];
      $this->length = 1;
      return;
    }
  }
}