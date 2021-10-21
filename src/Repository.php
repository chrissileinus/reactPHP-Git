<?php

namespace Chrissileinus\React\Git;

const forceUTF8 = true;

class Repository
{
  protected string $repository;

  /**
   * Open or Init repo in directory if it not exist
   * @param  string $repository
   * @param  string[]|null $initParams
   * @throws Exception
   */
  public function __construct(string $repository, array $initParams = null)
  {
    if (basename($repository) === '.git') {
      $repository = dirname($repository);
    }

    if (($noDir = !is_dir($repository)) && !@mkdir($repository, 0777, true)) {
      throw new Exception("Unable to create directory '{$repository}'.");
    }

    if ($noDir) user_error("Repository directory created '{$repository}'.");

    $this->repository = $repository;

    $this->run('init', $initParams);
  }

  public function path()
  {
    return $this->repository;
  }

  /**
   * Adds file(s).
   * `git add <file>`
   * @param  string|string[] $file
   * @return \React\Promise\PromiseInterface
   * @throws Exception
   */
  public function add($file)
  {
    if ($file == "*") {
      return $this->run('add', $file);
    }

    if (!is_array($file)) {
      $file = func_get_args();
    }

    $promises = [];
    foreach ($file as $item) {
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
   * @param  string $message
   * @param  string[] $params  param => value
   * @return \React\Promise\PromiseInterface
   * @throws Exception
   */
  public function commit(string $message, $params = null)
  {
    return $this->run('commit', $params, "-am \"{$message}\"")->then(function ($result) {
      if (preg_match_all('/nothing to commit, working tree clean/', $result, $matches)) throw new Exception('nothing to commit, working tree clean');
      return $result;
    });
  }

  /**
   * status?
   * `git status`
   * @param  string $message
   * @param  string[] $params  param => value
   * @return \React\Promise\PromiseInterface
   * @throws Exception
   */
  public function status()
  {
    return $this->run('status', '--short', '--untracked-files no')->then(function ($result) {
      if ($result == '') throw new Exception('nothing to commit, working tree clean');
      return $result;
    });
  }

  /**
   * get log command.
   * `git log`
   * @return \React\Promise\PromiseInterface|array
   * @throws Exception
   */
  public function log()
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
   * @return \React\Promise\PromiseInterface|array
   * @throws Exception
   */
  public function show(string $commit = '')
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
   * @param  string ...$args
   * @return \React\Promise\PromiseInterface
   * @throws Exception
   */
  public function run(...$args)
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
   * Runs command an stream the output.
   * @param  string ...$args
   * @return \React\Stream\ReadableStreamInterface
   * @throws Exception
   */
  public function runStream(...$args)
  {
    $command = 'git ' . helpers::stringifyArgs($args);

    $process = new \React\ChildProcess\Process($command, $this->repository);
    $process->start();

    return $process->stdout;
  }

  public static function echoThrowable()
  {
    return function (\Throwable $e) {
      if ($e instanceof Exception) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
        return;
      }

      echo (string) $e . PHP_EOL;
    };
  }
}
