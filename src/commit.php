<?php

namespace Chrissileinus\React\Git;

class commit
{
  static string $regex = '/^commit +(?<hash>.+)\nAuthor: +(?<authorName>.+) <(?<authorEmail>.*)>\nAuthorDate: +(?<authorDate>.+)\nCommit: +(?<commitName>.+) <(?<commitEmail>.*)>\nCommitDate: +(?<commitDate>.+)\n\n(?<comment>(?:.+\n)+)/m';

  public string $hash;
  public string $authorName;
  public string $authorEmail;
  public string $authorDate;
  public int $authorTimestamp;
  public string $commitName;
  public string $commitEmail;
  public string $commitDate;
  public int $commitTimestamp;
  public string $comment;

  function __construct(string $commit)
  {
    if (preg_match_all(self::$regex, $commit, $matches))
      foreach ($matches[0] as $key => $value) {
        $this->hash             = $matches['hash'][$key];
        $this->authorName       = $matches['authorName'][$key];
        $this->authorEmail      = $matches['authorEmail'][$key];
        $this->authorDate       = $matches['authorDate'][$key];
        $this->authorTimestamp  = strtotime($matches['authorDate'][$key]);
        $this->commitName       = $matches['commitName'][$key];
        $this->commitEmail      = $matches['commitEmail'][$key];
        $this->commitDate       = $matches['commitDate'][$key];
        $this->commitTimestamp  = strtotime($matches['commitDate'][$key]);
        $this->comment          = implode(PHP_EOL, array_map('trim', explode(PHP_EOL, trim($matches['comment'][$key]))));
      }
  }
}
