<?php

namespace Assegai\Core\WebComponents;

use Assegai\Core\Runtimes\RuntimeContext;
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
        return self::bundleTag(self::getBundleUrl($workingDirectory));
    }

    private static function bundleTag(?string $bundleUrl): string
    {
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
        $configuration = self::getConfiguration($workingDirectory);
        $tags = array_filter([
            self::bundleTag($configuration->getBundleUrl()),
            self::hotReloadTag($configuration->getHotReloadState()),
        ]);

        return implode(PHP_EOL, $tags);
    }

    /**
     * Resolves the configured Web Components bundle URL, if available.
     */
    public static function getBundleUrl(?string $workingDirectory = null): ?string
    {
        return self::getConfiguration($workingDirectory)->getBundleUrl();
    }

    /**
     * Renders the hot-reload polling script when `wc:watch` is active.
     */
    public static function renderHotReloadTag(?string $workingDirectory = null): string
    {
        return self::hotReloadTag(self::getConfiguration($workingDirectory)->getHotReloadState());
    }

    /** @param array{bundleUrl: string, interval: int, markerUrl: string}|null $state */
    private static function hotReloadTag(?array $state): string
    {
        if ($state === null) {
            return '';
        }

        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $markerUrl = json_encode($state['markerUrl'], $jsonFlags) ?: '""';
        $bundleUrl = json_encode($state['bundleUrl'], $jsonFlags) ?: '""';
        $interval = max(250, (int)($state['interval'] ?? self::DEFAULT_HOT_RELOAD_INTERVAL));

        return <<<HTML
<script>
(() => {
  const markerUrl = $markerUrl;
  const interval = $interval;
  const bundleUrl = $bundleUrl;
  let version = null;

  const tick = async () => {
    try {
      const markerResponse = await fetch(markerUrl + '?t=' + Date.now(), { cache: 'no-store' });

      if (!markerResponse.ok) {
        window.setTimeout(tick, interval);
        return;
      }

      const marker = await markerResponse.json();

      if (!marker || marker.active === false) {
        window.setTimeout(tick, interval);
        return;
      }

      const nextVersion = typeof marker.version === 'string' && marker.version !== ''
        ? marker.version
        : null;

      if (!nextVersion) {
        window.setTimeout(tick, interval);
        return;
      }

      if (version === null) {
        version = nextVersion;
        window.setTimeout(tick, interval);
        return;
      }

      if (marker.bundleUrl && marker.bundleUrl !== bundleUrl) {
        window.location.reload();
        return;
      }

      if (nextVersion !== version) {
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
     * Uses the active request snapshot only for its own project.
     */
    public static function getConfiguration(?string $workingDirectory = null): WebComponentConfiguration
    {
        $current = RuntimeContext::get(WebComponentConfiguration::class);
        if ($current instanceof WebComponentConfiguration && (
            $workingDirectory === null
            || (realpath($workingDirectory) ?: $workingDirectory) === $current->workingDirectory
        )) {
            return $current;
        }

        // Standalone helpers retain live workspace lookup without process-wide caching.
        return new WebComponentConfiguration($workingDirectory ?? Paths::getWorkingDirectory());
    }
}
