<?php

namespace Assegai\Core\WebComponents;

use Assegai\Core\Util\Paths;

/**
 * Shared helpers for server-rendered Web Components.
 */
final class WebComponentSupport
{
    public const string DEFAULT_BUNDLE_URL = '/js/assegai-components.min.js';

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
     * Normalizes a configured bundle path into a browser URL.
     */
    private static function normalizeBundleUrl(?string $bundlePath): ?string
    {
        if (!$bundlePath) {
            return null;
        }

        if (filter_var($bundlePath, FILTER_VALIDATE_URL)) {
            return $bundlePath;
        }

        $bundlePath = '/' . ltrim($bundlePath, '/');

        if (str_starts_with($bundlePath, '/public/')) {
            return substr($bundlePath, strlen('/public'));
        }

        return $bundlePath;
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
