<?php

namespace Assegai\Core\Exceptions\Handlers\Concerns;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Util\Paths;
use Throwable;

trait LogsHandledExceptions
{
  protected function logHandledException(Throwable $exception): void
  {
    $message = $this->formatThrowableForLog($exception);

    if ($exception instanceof HttpException && $exception->getStatus()->code < 500) {
      $this->logger->debug($message);
      return;
    }

    $this->logger->error($message);
  }

  protected function formatThrowableForLog(Throwable $exception): string
  {
    $entries = [];
    $applicationFrame = $this->resolveApplicationFrame($exception);

    if (is_array($applicationFrame)) {
      $entries[] = 'Application frame: ' . $this->formatApplicationFrame($applicationFrame);
    }

    $index = 0;

    do {
      $heading = $index === 0
        ? get_class($exception)
        : 'Previous exception #' . $index . ' ' . get_class($exception);
      $trace = $exception->getTraceAsString();

      $entries[] = sprintf(
        "%s: %s in %s on line %d%s%s",
        $heading,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $trace !== '' ? PHP_EOL . 'Stack trace:' . PHP_EOL : '',
        $trace,
      );

      $exception = $exception->getPrevious();
      $index++;
    } while ($exception instanceof Throwable);

    return implode(PHP_EOL . PHP_EOL, $entries);
  }

  /**
   * @return array{file: string, line: int, class?: string, function?: string}|null
   */
  protected function resolveApplicationFrame(Throwable $exception): ?array
  {
    $applicationRoots = $this->resolveApplicationRootPaths();

    do {
      if ($this->isApplicationFile($exception->getFile(), $applicationRoots)) {
        return [
          'file' => $exception->getFile(),
          'line' => $exception->getLine(),
        ];
      }

      foreach ($exception->getTrace() as $frame) {
        $file = $frame['file'] ?? null;

        if (!is_string($file) || !$this->isApplicationFile($file, $applicationRoots)) {
          continue;
        }

        $applicationFrame = [
          'file' => $file,
          'line' => is_int($frame['line'] ?? null) ? $frame['line'] : 0,
          'function' => $frame['function'],
        ];

        if (is_string($frame['class'] ?? null)) {
          $applicationFrame['class'] = $frame['class'];
        }

        return $applicationFrame;
      }

      $exception = $exception->getPrevious();
    } while ($exception instanceof Throwable);

    return null;
  }

  /**
   * @return list<string>
   */
  protected function resolveApplicationRootPaths(): array
  {
    $paths = [];

    foreach ([getenv('ASSEGAI_WORKING_DIR') ?: null, Paths::getWorkingDirectory(), getcwd() ?: null] as $candidate) {
      if (!is_string($candidate) || trim($candidate) === '') {
        continue;
      }

      $realPath = realpath($candidate) ?: $candidate;
      $paths[] = $this->normalizePath($realPath);
    }

    return array_values(array_unique($paths));
  }

  /**
   * @param list<string> $applicationRoots
   */
  protected function isApplicationFile(string $file, array $applicationRoots): bool
  {
    $path = realpath($file) ?: $file;
    $path = $this->normalizePath($path);

    if ($this->isInternalFrameworkFile($path)) {
      return false;
    }

    foreach ($applicationRoots as $rootPath) {
      if ($this->pathStartsWith($path, $rootPath)) {
        return true;
      }
    }

    return false;
  }

  protected function isInternalFrameworkFile(string $path): bool
  {
    $path = $this->normalizePath($path);
    $coreRoot = $this->normalizePath(dirname(__DIR__, 4));

    return str_contains($path, '/vendor/')
      || $this->pathStartsWith($path, $coreRoot . '/src')
      || $this->pathStartsWith($path, $coreRoot . '/vendor');
  }

  /**
   * @param array{file: string, line: int, class?: string|null, function?: string|null} $frame
   */
  protected function formatApplicationFrame(array $frame): string
  {
    $location = $frame['file'] . ':' . $frame['line'];
    $class = $frame['class'] ?? null;
    $function = $frame['function'] ?? null;

    if (!$function) {
      return $location;
    }

    return $location . ' via ' . ($class ? $class . '::' : '') . $function . '()';
  }

  protected function pathStartsWith(string $path, string $rootPath): bool
  {
    $path = $this->normalizePath($path);
    $rootPath = $this->normalizePath($rootPath);

    return $path === $rootPath || str_starts_with($path, rtrim($rootPath, '/') . '/');
  }

  protected function normalizePath(string $path): string
  {
    return rtrim(str_replace('\\', '/', $path), '/');
  }
}
