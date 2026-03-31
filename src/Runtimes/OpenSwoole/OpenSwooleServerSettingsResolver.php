<?php

namespace Assegai\Core\Runtimes\OpenSwoole;

use InvalidArgumentException;

final class OpenSwooleServerSettingsResolver
{
  /**
   * @var array<string, true>
   */
  private const array SUPPORTED_SETTINGS = [
    'workerNum' => true,
    'taskWorkerNum' => true,
    'maxRequest' => true,
    'enableCoroutine' => true,
    'hookFlags' => true,
    'daemonize' => true,
    'logFile' => true,
    'pidFile' => true,
  ];

  /**
   * @param array<string, mixed> $settings
   * @return array<string, mixed>
   */
  public function normalize(array $settings): array
  {
    $this->assertSupportedSettings($settings);

    $normalized = [];

    if (array_key_exists('workerNum', $settings)) {
      $normalized['workerNum'] = $this->normalizeIntegerSetting('workerNum', $settings['workerNum'], 1);
    }

    if (array_key_exists('taskWorkerNum', $settings)) {
      $normalized['taskWorkerNum'] = $this->normalizeIntegerSetting('taskWorkerNum', $settings['taskWorkerNum'], 0);
    }

    if (array_key_exists('maxRequest', $settings)) {
      $normalized['maxRequest'] = $this->normalizeIntegerSetting('maxRequest', $settings['maxRequest'], 0);
    }

    if (array_key_exists('enableCoroutine', $settings)) {
      $normalized['enableCoroutine'] = $this->normalizeBooleanSetting('enableCoroutine', $settings['enableCoroutine']);
    }

    if (array_key_exists('hookFlags', $settings)) {
      $normalized['hookFlags'] = $this->normalizeHookFlags($settings['hookFlags']);
    }

    if (array_key_exists('daemonize', $settings)) {
      $normalized['daemonize'] = $this->normalizeBooleanSetting('daemonize', $settings['daemonize']);
    }

    if (array_key_exists('logFile', $settings)) {
      $normalized['logFile'] = $this->normalizeStringSetting('logFile', $settings['logFile']);
    }

    if (array_key_exists('pidFile', $settings)) {
      $normalized['pidFile'] = $this->normalizeStringSetting('pidFile', $settings['pidFile']);
    }

    return $normalized;
  }

  /**
   * @param array<string, mixed> $settings
   * @return array<string, mixed>
   */
  public function toServerSettings(array $settings): array
  {
    $normalized = $this->normalize($settings);
    $serverSettings = [
      'enable_coroutine' => $normalized['enableCoroutine'] ?? true,
    ];

    if (array_key_exists('hookFlags', $normalized)) {
      $resolvedHookFlags = $this->resolveHookFlagsForServer($normalized['hookFlags']);

      if ($resolvedHookFlags !== null) {
        $serverSettings['hook_flags'] = $resolvedHookFlags;
      }
    }

    $optionalMappings = [
      'workerNum' => 'worker_num',
      'taskWorkerNum' => 'task_worker_num',
      'maxRequest' => 'max_request',
      'daemonize' => 'daemonize',
      'logFile' => 'log_file',
      'pidFile' => 'pid_file',
    ];

    foreach ($optionalMappings as $sourceKey => $targetKey) {
      if (!array_key_exists($sourceKey, $normalized)) {
        continue;
      }

      $serverSettings[$targetKey] = $normalized[$sourceKey];
    }

    return $serverSettings;
  }

  /**
   * @param array<string, mixed> $settings
   */
  private function assertSupportedSettings(array $settings): void
  {
    foreach (array_keys($settings) as $key) {
      if (!is_string($key) || isset(self::SUPPORTED_SETTINGS[$key])) {
        continue;
      }

      throw new InvalidArgumentException(sprintf(
        'Unsupported OpenSwoole setting [%s]. Supported settings are: %s.',
        $key,
        implode(', ', array_keys(self::SUPPORTED_SETTINGS)),
      ));
    }
  }

  private function normalizeIntegerSetting(string $name, mixed $value, int $minimum): int
  {
    if (is_string($value)) {
      $value = trim($value);
    }

    if ($value === '' || $value === null) {
      throw new InvalidArgumentException(sprintf('The OpenSwoole setting [%s] must be an integer.', $name));
    }

    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
      throw new InvalidArgumentException(sprintf('The OpenSwoole setting [%s] must be an integer.', $name));
    }

    $intValue = (int) $value;

    if ($intValue < $minimum) {
      $comparison = $minimum === 1 ? 'greater than or equal to 1' : sprintf('greater than or equal to %d', $minimum);
      throw new InvalidArgumentException(sprintf('The OpenSwoole setting [%s] must be %s.', $name, $comparison));
    }

    return $intValue;
  }

  private function normalizeBooleanSetting(string $name, mixed $value): bool
  {
    if (is_bool($value)) {
      return $value;
    }

    $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

    if ($normalized === null) {
      throw new InvalidArgumentException(sprintf('The OpenSwoole setting [%s] must be a boolean.', $name));
    }

    return $normalized;
  }

  private function normalizeStringSetting(string $name, mixed $value): string
  {
    if (!is_scalar($value)) {
      throw new InvalidArgumentException(sprintf('The OpenSwoole setting [%s] must be a non-empty string.', $name));
    }

    $normalized = trim((string) $value);

    if ($normalized === '') {
      throw new InvalidArgumentException(sprintf('The OpenSwoole setting [%s] must be a non-empty string.', $name));
    }

    return $normalized;
  }

  private function normalizeHookFlags(mixed $hookFlags): mixed
  {
    if ($hookFlags === null || $hookFlags === false) {
      return null;
    }

    if ($hookFlags === true) {
      return 'all';
    }

    if (is_int($hookFlags)) {
      return $hookFlags;
    }

    if (is_string($hookFlags)) {
      $trimmed = trim($hookFlags);

      if ($trimmed === '') {
        return null;
      }

      if (str_contains($trimmed, ',')) {
        $parts = array_values(array_filter(array_map('trim', explode(',', $trimmed)), static fn(string $token): bool => $token !== ''));

        if ($parts === []) {
          return null;
        }

        return array_map(fn(string $token): string => $this->normalizeHookFlagToken($token), $parts);
      }

      if (filter_var($trimmed, FILTER_VALIDATE_INT) !== false) {
        return (int) $trimmed;
      }

      return $this->normalizeHookFlagToken($trimmed);
    }

    if (is_array($hookFlags)) {
      $tokens = [];

      foreach ($hookFlags as $token) {
        if (!is_scalar($token)) {
          throw new InvalidArgumentException('The OpenSwoole setting [hookFlags] must contain only strings or integers.');
        }

        $tokens[] = $this->normalizeHookFlagToken((string) $token);
      }

      return $tokens === [] ? null : $tokens;
    }

    throw new InvalidArgumentException('The OpenSwoole setting [hookFlags] must be an integer, string, list, or null.');
  }

  private function normalizeHookFlagToken(string $token): string
  {
    $normalized = strtolower(trim($token));

    if ($normalized === '') {
      throw new InvalidArgumentException('The OpenSwoole setting [hookFlags] contains an empty flag name.');
    }

    if (!preg_match('/^[a-z0-9_-]+$/', $normalized)) {
      throw new InvalidArgumentException(sprintf(
        'The OpenSwoole setting [hookFlags] contains an invalid flag [%s].',
        $token,
      ));
    }

    return $normalized;
  }

  private function resolveHookFlagsForServer(mixed $hookFlags): mixed
  {
    if ($hookFlags === null || $hookFlags === false) {
      return null;
    }

    if (is_int($hookFlags)) {
      return $hookFlags;
    }

    if (is_string($hookFlags)) {
      return $this->resolveSingleHookFlagForServer($hookFlags);
    }

    if (is_array($hookFlags)) {
      $combined = 0;

      foreach ($hookFlags as $token) {
        $resolved = $this->resolveSingleHookFlagForServer($token);

        if (!is_int($resolved)) {
          return $hookFlags;
        }

        $combined |= $resolved;
      }

      return $combined;
    }

    return $hookFlags;
  }

  private function resolveSingleHookFlagForServer(string $token): mixed
  {
    $normalized = $this->normalizeHookFlagToken($token);

    if ($normalized === 'none') {
      return null;
    }

    $constantName = str_starts_with(strtoupper($normalized), 'SWOOLE_HOOK_')
      ? strtoupper($normalized)
      : 'SWOOLE_HOOK_' . strtoupper(str_replace('-', '_', $normalized));

    if (defined($constantName)) {
      return constant($constantName);
    }

    return $normalized;
  }
}
