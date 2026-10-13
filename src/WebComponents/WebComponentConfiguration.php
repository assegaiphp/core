<?php

namespace Assegai\Core\WebComponents;

use Assegai\Core\Util\Paths;
use InvalidArgumentException;

/**
 * A lazy, project-bound rendering snapshot. Create a new instance for each request.
 */
final class WebComponentConfiguration
{
    public readonly string $workingDirectory;

    /** @var array<string, mixed> */
    private readonly array $overrides;
    private bool $resolved = false;
    private bool $bundleEnabled = true;
    private bool $hotReloadEnabled = true;
    private ?string $bundleUrl = null;
    /** @var array{bundleUrl: string, interval: int, markerUrl: string}|null */
    private ?array $hotReloadState = null;

    public function __construct(string $workingDirectory, mixed $overrides = [])
    {
        $this->workingDirectory = realpath($workingDirectory) ?: $workingDirectory;
        if (!is_array($overrides)) {
            throw new InvalidArgumentException('Application webComponents configuration must be an array.');
        }
        self::validateOverrides($overrides);
        $this->overrides = $overrides;
    }

    public function getBundleUrl(): ?string
    {
        $this->resolve();
        return $this->bundleUrl;
    }

    public function isBundleEnabled(): bool
    {
        $this->resolve();
        return $this->bundleEnabled;
    }

    public function isHotReloadEnabled(): bool
    {
        $this->resolve();
        return $this->hotReloadEnabled;
    }

    /** @return array{bundleUrl: string, interval: int, markerUrl: string}|null */
    public function getHotReloadState(): ?array
    {
        $this->resolve();
        return $this->hotReloadState;
    }

    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $config = array_replace_recursive($this->loadWorkspaceConfig(), $this->overrides);
        $this->bundleEnabled = ($config['enabled'] ?? true) !== false;
        $this->hotReloadEnabled = ($config['hotReload']['enabled'] ?? true) !== false;
        $this->bundleUrl = $this->resolveBundleUrl($config);
        $this->hotReloadState = $this->resolveHotReloadState($config, $this->bundleUrl);
        $this->resolved = true;
    }

    /** @return array<string, mixed> */
    private function loadWorkspaceConfig(): array
    {
        $config = self::readJson(Paths::join($this->workingDirectory, 'assegai.json'));
        return is_array($config['webComponents'] ?? null) ? $config['webComponents'] : [];
    }

    /** @param array<string, mixed> $config */
    private function resolveBundleUrl(array $config): ?string
    {
        if (($config['enabled'] ?? null) === false) {
            return null;
        }

        $url = self::normalizePublicUrl($config['bundleUrl'] ?? $config['bundlePath'] ?? $config['output'] ?? null)
            ?? WebComponentSupport::DEFAULT_BUNDLE_URL;

        return filter_var($url, FILTER_VALIDATE_URL) || is_file($this->publicPath($url)) ? $url : null;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{bundleUrl: string, interval: int, markerUrl: string}|null
     */
    private function resolveHotReloadState(array $config, ?string $bundleUrl): ?array
    {
        $hotReload = is_array($config['hotReload'] ?? null) ? $config['hotReload'] : [];
        if (($hotReload['enabled'] ?? true) === false) {
            return null;
        }

        $markerUrl = self::normalizePublicUrl($hotReload['path'] ?? WebComponentSupport::DEFAULT_HOT_RELOAD_URL);
        if ($markerUrl === null) {
            return null;
        }

        $state = self::readJson($this->publicPath($markerUrl));
        if (($state['active'] ?? false) !== true) {
            return null;
        }

        $expiresAt = isset($state['expiresAt']) ? strtotime((string)$state['expiresAt']) : false;
        if ($expiresAt !== false && $expiresAt < time()) {
            return null;
        }

        $bundleUrl = self::normalizePublicUrl($state['bundleUrl'] ?? $bundleUrl);
        if ($bundleUrl === null) {
            return null;
        }

        return [
            'bundleUrl' => $bundleUrl,
            'interval' => (int)($state['interval'] ?? $hotReload['pollInterval'] ?? WebComponentSupport::DEFAULT_HOT_RELOAD_INTERVAL),
            'markerUrl' => $markerUrl,
        ];
    }

    /** @return array<string, mixed> */
    private static function readJson(string $filename): array
    {
        if (!is_file($filename)) {
            return [];
        }
        $contents = file_get_contents($filename);
        if (!$contents || !json_is_valid($contents)) {
            return [];
        }
        $value = json_decode($contents, true);
        return is_array($value) ? $value : [];
    }

    private static function normalizePublicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
        $path = '/' . ltrim($path, '/');
        return str_starts_with($path, '/public/') ? substr($path, strlen('/public')) : $path;
    }

    private function publicPath(string $url): string
    {
        return Paths::join($this->workingDirectory, 'public', ltrim($url, '/'));
    }

    /** @param array<string, mixed> $overrides */
    private static function validateOverrides(array $overrides): void
    {
        foreach ($overrides as $key => $value) {
            $valid = match ($key) {
                'enabled' => is_bool($value),
                'bundleUrl' => is_string($value) && trim($value) !== '',
                'hotReload' => is_array($value),
                default => false,
            };
            if (!$valid) {
                throw new InvalidArgumentException("Invalid application webComponents.$key configuration.");
            }
        }
        foreach ($overrides['hotReload'] ?? [] as $key => $value) {
            $valid = match ($key) {
                'enabled' => is_bool($value),
                'path' => is_string($value) && trim($value) !== '',
                'pollInterval' => is_int($value) && $value > 0,
                default => false,
            };
            if (!$valid) {
                throw new InvalidArgumentException("Invalid application webComponents.hotReload.$key configuration.");
            }
        }
    }
}
