<?php

namespace Assegai\Core\Runtimes;

final class RuntimeContext
{
  /**
   * @var array<string, array<string, mixed>>
   */
  private static array $store = [];

  /**
   * Stores a value for the active runtime execution context.
   *
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public static function set(string $key, mixed $value): void
  {
    self::$store[self::getContextId()][$key] = $value;
  }

  /**
   * Returns a value from the active runtime execution context.
   *
   * @param string $key
   * @param mixed $default
   * @return mixed
   */
  public static function get(string $key, mixed $default = null): mixed
  {
    return self::$store[self::getContextId()][$key] ?? $default;
  }

  /**
   * Removes a value from the active runtime execution context.
   *
   * @param string $key
   * @return void
   */
  public static function forget(string $key): void
  {
    unset(self::$store[self::getContextId()][$key]);
  }

  /**
   * Clears the provided keys or the whole active runtime execution context.
   *
   * @param string[] $keys
   * @return void
   */
  public static function flush(array $keys = []): void
  {
    $contextId = self::getContextId();

    if ($keys === []) {
      unset(self::$store[$contextId]);
      return;
    }

    foreach ($keys as $key) {
      unset(self::$store[$contextId][$key]);
    }

    if (empty(self::$store[$contextId])) {
      unset(self::$store[$contextId]);
    }
  }

  /**
   * Returns the current execution context identifier.
   *
   * When OpenSwoole/Swoole coroutines are available, the coroutine id becomes the
   * context key. Otherwise everything falls back to the main PHP request context.
   *
   * @return string
   */
  public static function getContextId(): string
  {
    $coroutineId = self::resolveCoroutineId();

    if (null === $coroutineId || $coroutineId < 0) {
      return 'main';
    }

    return 'coroutine:' . $coroutineId;
  }

  /**
   * @return int|null
   */
  private static function resolveCoroutineId(): ?int
  {
    $candidates = [
      '\\OpenSwoole\\Coroutine',
      '\\Swoole\\Coroutine',
    ];

    foreach ($candidates as $candidate) {
      if (!class_exists($candidate) || !method_exists($candidate, 'getCid')) {
        continue;
      }

      /** @var int $cid */
      $cid = $candidate::getCid();
      return $cid;
    }

    return null;
  }
}
