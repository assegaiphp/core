<?php

namespace Assegai\Core\WebComponents;

use Assegai\Core\Util\Paths;

/**
 * Shared helpers for server-rendered Web Components.
 */
final class WebComponentSupport
{
    public const string DEFAULT_BUNDLE_URL = '/js/assegai-components.min.js';
    public const string DEFAULT_HOT_RELOAD_URL = '/.assegai/wc-hot-reload.json';
    public const int DEFAULT_HOT_RELOAD_INTERVAL = 1000;

    private final function __construct()
    {
    }

    /**
     * Encodes component props for safe use inside an HTML attribute.
     *
     * @param mixed $props
     * @return string
     */
    public static function encodeProps(mixed $props = []): string
    {
        $json = json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            $json = '{}';
        }

        return htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Renders the module script tag for the configured Web Components bundle.
     */
    public static function renderBundleTag(?string $workingDirectory = null): string
    {
        $bundleUrl = self::getBundleUrl($workingDirectory);

        if ($bundleUrl === null) {
            return '';
        }

        $escapedUrl = htmlspecialchars($bundleUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "<script type=\"module\" src=\"$escapedUrl\"></script>";
    }

    /**
     * Renders all runtime tags needed for Web Components in the current workspace.
     */
    public static function renderRuntimeTags(?string $workingDirectory = null): string
    {
        $tags = array_filter([
            self::renderBundleTag($workingDirectory),
            self::renderHotReloadTag($workingDirectory),
        ]);

        return implode(PHP_EOL, $tags);
    }

    /**
     * Resolves the configured Web Components bundle URL, if available.
     */
    public static function getBundleUrl(?string $workingDirectory = null): ?string
    {
        $workingDirectory ??= getcwd() ?: '.';
        $config = self::loadConfig($workingDirectory);

        if (($config['enabled'] ?? null) === false) {
            return null;
        }

        $configuredBundleUrl = self::normalizeBundleUrl(
            $config['bundleUrl'] ?? $config['bundlePath'] ?? $config['output'] ?? null
        );

        if ($configuredBundleUrl !== null) {
            if (($config['enabled'] ?? null) === true || self::bundleExists($configuredBundleUrl, $workingDirectory)) {
                return $configuredBundleUrl;
            }

            return null;
        }

        return self::bundleExists(self::DEFAULT_BUNDLE_URL, $workingDirectory)
            ? self::DEFAULT_BUNDLE_URL
            : null;
    }

    /**
     * Renders the hot-reload polling script when `wc:watch` is active.
     */
    public static function renderHotReloadTag(?string $workingDirectory = null): string
    {
        $state = self::getHotReloadState($workingDirectory);

        if ($state === null) {
            return '';
        }

        $markerUrl = json_encode($state['markerUrl'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '""';
        $bundleUrl = json_encode($state['bundleUrl'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '""';
        $interval = max(250, (int)($state['interval'] ?? self::DEFAULT_HOT_RELOAD_INTERVAL));

        return <<<HTML
<script>
(() => {
  const markerUrl = $markerUrl;
  const bundleUrl = $bundleUrl;
  const interval = $interval;
  let signature = null;

  const readSignature = async () => {
    const response = await fetch(bundleUrl + '?t=' + Date.now(), {
      method: 'HEAD',
      cache: 'no-store',
    });

    if (!response.ok) {
      return null;
    }

    return [
      response.headers.get('etag'),
      response.headers.get('last-modified'),
      response.headers.get('content-length'),
    ].filter(Boolean).join('|') || null;
  };

  const tick = async () => {
    try {
      const markerResponse = await fetch(markerUrl + '?t=' + Date.now(), { cache: 'no-store' });

      if (!markerResponse.ok) {
        return;
      }

      const marker = await markerResponse.json();

      if (!marker || marker.active === false) {
        return;
      }

      const nextSignature = await readSignature();

      if (!nextSignature) {
        window.setTimeout(tick, interval);
        return;
      }

      if (signature === null) {
        signature = nextSignature;
        window.setTimeout(tick, interval);
        return;
      }

      if (nextSignature !== signature) {
        window.location.reload();
        return;
      }
    } catch (error) {
      console.debug('[Assegai WC] Hot reload polling stopped.', error);
      return;
    }

    window.setTimeout(tick, interval);
  };

  window.setTimeout(tick, interval);
})();
</script>
HTML;
    }

    /**
     * Loads the Web Components section from the workspace config if it exists.
     *
     * @return array<string, mixed>
     */
    private static function loadConfig(string $workingDirectory): array
    {
        $configFilename = Paths::join($workingDirectory, 'assegai.json');

        if (!is_file($configFilename)) {
            return [];
        }

        $contents = file_get_contents($configFilename);

        if (!$contents || !json_is_valid($contents)) {
            return [];
        }

        $config = json_decode($contents, true);

        if (!is_array($config)) {
            return [];
        }

        return is_array($config['webComponents'] ?? null)
            ? $config['webComponents']
            : [];
    }

    /**
     * Resolves the current hot-reload state when `wc:watch` is active.
     *
     * @return array{bundleUrl: string, interval: int, markerUrl: string}|null
     */
    private static function getHotReloadState(?string $workingDirectory = null): ?array
    {
        $workingDirectory ??= getcwd() ?: '.';
        $config = self::loadConfig($workingDirectory);
        $hotReloadConfig = is_array($config['hotReload'] ?? null)
            ? $config['hotReload']
            : [];

        if (($hotReloadConfig['enabled'] ?? true) === false) {
            return null;
        }

        $markerUrl = self::normalizePublicUrl($hotReloadConfig['path'] ?? self::DEFAULT_HOT_RELOAD_URL);

        if ($markerUrl === null) {
            return null;
        }

        $markerFilename = Paths::join($workingDirectory, 'public', ltrim($markerUrl, '/'));

        if (!is_file($markerFilename)) {
            return null;
        }

        $contents = file_get_contents($markerFilename);

        if (!$contents || !json_is_valid($contents)) {
            return null;
        }

        $state = json_decode($contents, true);

        if (!is_array($state) || ($state['active'] ?? false) !== true) {
            return null;
        }

        $expiresAt = isset($state['expiresAt']) ? strtotime((string)$state['expiresAt']) : false;

        if ($expiresAt !== false && $expiresAt < time()) {
            return null;
        }

        $bundleUrl = self::normalizePublicUrl($state['bundleUrl'] ?? self::getBundleUrl($workingDirectory));

        if ($bundleUrl === null) {
            return null;
        }

        return [
            'bundleUrl' => $bundleUrl,
            'interval' => (int)($state['interval'] ?? $hotReloadConfig['pollInterval'] ?? self::DEFAULT_HOT_RELOAD_INTERVAL),
            'markerUrl' => $markerUrl,
        ];
    }

    /**
     * Normalizes a configured bundle path into a browser URL.
     */
    private static function normalizeBundleUrl(?string $bundlePath): ?string
    {
        return self::normalizePublicUrl($bundlePath);
    }

    /**
     * Normalizes a configured public asset path into a browser URL.
     */
    private static function normalizePublicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = '/' . ltrim($path, '/');

        if (str_starts_with($path, '/public/')) {
            return substr($path, strlen('/public'));
        }

        return $path;
    }

    /**
     * Checks whether the bundle exists locally for a given browser URL.
     */
    private static function bundleExists(string $bundleUrl, string $workingDirectory): bool
    {
        if (filter_var($bundleUrl, FILTER_VALIDATE_URL)) {
            return true;
        }

        $bundleFilename = Paths::join($workingDirectory, 'public', ltrim($bundleUrl, '/'));

        return is_file($bundleFilename);
    }
}
