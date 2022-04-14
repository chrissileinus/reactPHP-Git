<?php
/*
 * Created on Wed Nov 03 2021
 *
 * Copyright (c) 2021 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\Git;

const forceUTF8 = true;

class Repository
{
  protected string $repository;
  protected $onError = null;

  /**
   * Open or Init repo in directory if it not exist
   *
   * @param  string $repository
   * @param  string ...$initParams
   * @throws Exception
   */
  public function __construct(string $repository, callable $onError = null, array $gitUser = [], string ...$initParams)
  {
    if (basename($repository) === '.git') {
      $repository = dirname($repository);
    }

    if (($noDir = !is_dir($repository)) && !@mkdir($repository, 0777, true)) {
      throw new Exception("Unable to create directory '{$repository}'.");
    }

    if ($noDir) user_error("Repository directory created '{$repository}'.");

    $this->repository = $repository;
    $this->onError = $onError;

    $this->run('init', ...$initParams);

    $gitUser['email'] ??= "D1ca@service.local";
    $gitUser['name'] ??= "D1ca";
    $this->run('config', "user.email \"{$gitUser['email']}\"");
    $this->run('config', "user.name \"{$gitUser['name']}\"");
  }

  /**
   * path
   *
   * @return string
   */
  public function path(): string
  {
    return $this->repository;
  }

  /**
   * Adds file(s).
   * `git add <...files>`
   *
   * @param  string ...$files
   * @return \React\Promise\PromiseInterface
   * @throws Exception
   */
  public function add(string ...$files): \React\Promise\PromiseInterface
  {
    if ($files[0] == "*" || $files[0] == ".") {
      return $this->run('add', $files[0]);
    }

    $promises = [];
    foreach ($files as $item) {
      // make sure the given item exists
      // this can be a file or an directory, git supports both
      $path = helpers::isAbsolute($item) ? $item : ($this->repository . DIRECTORY_SEPARATOR . $item);

      if (!file_exists($path)) {
        throw new Exception("The path at '$item' does not represent a valid file.");
      }

      $promises[] = $this->run('add', $item);
    }

    return \React\Promise\all($promises);
  }

  /**
   * commit changes
   * `git commit <params> -m <message>`
   *
   * @param  string $message
   * @param  string ...$params
   * @return \React\Promise\PromiseInterface
   */
  public function commit(string $message, string ...$params): \React\Promise\PromiseInterface
  {
    return $this->run('commit', "-am \"{$message}\"", ...$params)->then(function ($result) {
      if (preg_match_all('/nothing to commit, working tree clean/', $result, $matches)) throw new Exception('nothing to commit, working tree clean');
      return $result;
    });
  }

  /**
   * status?
   * `git status`
   *
   * @return \React\Promise\PromiseInterface
   */
  public function status(): \React\Promise\PromiseInterface
  {
    return $this->run('status', '--short', '--untracked-files no')->then(function ($result) {
      if ($result == '') throw new Exception('nothing to commit, working tree clean');
      return $result;
    });
  }

  /**
   * get log command.
   * `git log`
   *
   * @return \React\Promise\PromiseInterface
   */
  public function log(): \React\Promise\PromiseInterface
  {
    return $this->run('log', '--format=fuller')->then(function ($result) {
      try {
        $commits = [];
        if (preg_match_all(commit::$regex, $result, $matches))
          foreach ($matches[0] as $value) {
            $commits[] = new commit($value);
          }
        return $commits;
      } catch (\Throwable $th) {
        throw $th;
      }
    });
  }

  /**
   * show commit.
   * `git show`
   *
   * @return \React\Promise\PromiseInterface
   * @throws Exception
   */
  public function show(string $commit = ''): \React\Promise\PromiseInterface
  {
    return $this->run('show', '--format=fuller', $commit)->then(function ($result) {
      try {
        $diffs = [];
        if (preg_match_all(diff::$regex, $result, $matches))
          foreach ($matches[0] as $value) {
            $diffs[] = new diff($value);
          }

        return [
          'commit' => new commit($result),
          'diffs' => $diffs,
        ];
      } catch (\Throwable $th) {
        throw $th;
      }
    });
  }

  /**
   * Runs command.
   *
   * @param  string ...$args
   * @return \React\Promise\PromiseInterface
   * @throws Exception
   */
  public function run(...$args): \React\Promise\PromiseInterface
  {
    $deferred = new \React\Promise\Deferred();

    $stdout = $this->runStream(...$args);

    $stdout->on('data', function ($chunk) use (&$result) {
      $result .= $chunk;
    });

    $stdout->on('close', function () use (&$result, $deferred) {
      $deferred->resolve($result);
    });

    return $deferred->promise();
  }

  /**
   * Runs command and stream the output.
   *
   * @param  string ...$args
   * @return \React\Stream\ReadableStreamInterface
   */
  public function runStream(...$args): \React\Stream\ReadableStreamInterface
  {
    $command = 'git ' . implode(' ', $args);

    $process = new \React\ChildProcess\Process($command, $this->repository);
    $process->start();


    $process->stderr->on('data', function ($chunk) use ($command) {
      call_user_func($this->onError, $command . PHP_EOL . $chunk);
    });

    return $process->stdout;
  }
}
