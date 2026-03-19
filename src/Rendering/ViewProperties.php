<?php

namespace Assegai\Core\Rendering;

use Assegai\Core\Config as FrameworkConfig;
use Assegai\Core\Enumerations\ViewType;

/**
 * Shared document-level properties for rendered HTML responses.
 */
class ViewProperties
{
  /**
   * The default view type.
   */
  const ViewType DEFAULT_TYPE = ViewType::HTML;

  /**
   * The default document title.
   */
  const string DEFAULT_TITLE = 'AssegaiPHP';

  /**
   * The default inline styles.
   */
  const array DEFAULT_STYLES = [];

  /**
   * The default linked stylesheets.
   */
  const array DEFAULT_LINKS = ['/css/style.css'];

  /**
   * The default inline head scripts.
   */
  const array DEFAULT_HEAD_SCRIPTS = [];

  /**
   * The default inline body scripts.
   */
  const array DEFAULT_BODY_SCRIPTS = [];

  /**
   * The default external head scripts.
   */
  const array DEFAULT_HEAD_SCRIPT_URLS = ['/js/main.js'];

  /**
   * The default external body scripts.
   */
  const array DEFAULT_BODY_SCRIPT_URLS = [];

  /**
   * The default base URL.
   */
  const string DEFAULT_BASE = '/';

  /**
   * The default document language.
   */
  const string DEFAULT_LANG = 'en';

  /**
   * The default favicon.
   */
  const array DEFAULT_FAVICON = ['/favicon.ico', 'image/x-icon'];

  /**
   * Tag indentation.
   */
  private const string PADDING = '  ';

  /**
   * @var ViewMeta
   */
  public readonly ViewMeta $meta;

  /**
   * @param ViewType $type
   * @param string $title
   * @param array<int, string> $styles
   * @param ViewMeta|array<string, mixed> $meta
   * @param array<int, string|array<int, string>> $links
   * @param array<int, string> $headScripts
   * @param array<int, string> $bodyScripts
   * @param array<int, string> $headScriptUrls
   * @param array<int, string> $bodyScriptUrls
   * @param string $base
   * @param string $lang
   * @param array<int, string> $favicon
   */
  public function __construct(
    public readonly ViewType $type = self::DEFAULT_TYPE,
    public readonly string $title = self::DEFAULT_TITLE,
    public readonly array $styles = self::DEFAULT_STYLES,
    ViewMeta|array $meta = new ViewMeta(),
    public readonly array $links = self::DEFAULT_LINKS,
    public readonly array $headScripts = self::DEFAULT_HEAD_SCRIPTS,
    public readonly array $bodyScripts = self::DEFAULT_BODY_SCRIPTS,
    public readonly array $headScriptUrls = self::DEFAULT_HEAD_SCRIPT_URLS,
    public readonly array $bodyScriptUrls = self::DEFAULT_BODY_SCRIPT_URLS,
    public readonly string $base = self::DEFAULT_BASE,
    public readonly string $lang = self::DEFAULT_LANG,
    public readonly array $favicon = self::DEFAULT_FAVICON
  )
  {
    $this->meta = is_array($meta) ? ViewMeta::fromArray($meta) : $meta;
  }

  /**
   * @return string
   */
  public function __toString(): string
  {
    return $this->generateMetaTags()
      . $this->generateTitleTag()
      . $this->generateHeadAssetTags();
  }

  /**
   * Builds view properties from per-view props merged onto the global `app`
   * document config in `config/default.php`.
   *
   * @param array<string, mixed> $props
   */
  public static function fromArray(array $props): self
  {
    $documentConfig = self::getDocumentConfig();

    return new self(
      type: array_key_exists('type', $props) ? $props['type'] : ($documentConfig['type'] ?? self::DEFAULT_TYPE),
      title: array_key_exists('title', $props) ? (string)$props['title'] : (string)($documentConfig['title'] ?? self::DEFAULT_TITLE),
      styles: self::mergeLists(self::resolveConfigList($documentConfig, 'styles', self::DEFAULT_STYLES), $props, 'styles'),
      meta: self::resolveMeta($documentConfig, $props),
      links: self::mergeLists(self::resolveConfigList($documentConfig, 'links', self::DEFAULT_LINKS), $props, 'links'),
      headScripts: self::mergeLists(self::resolveConfigList($documentConfig, 'headScripts', self::DEFAULT_HEAD_SCRIPTS), $props, 'headScripts'),
      bodyScripts: self::mergeLists(self::resolveConfigList($documentConfig, 'bodyScripts', self::DEFAULT_BODY_SCRIPTS), $props, 'bodyScripts'),
      headScriptUrls: self::mergeLists(self::resolveConfigList($documentConfig, 'headScriptUrls', self::DEFAULT_HEAD_SCRIPT_URLS), $props, 'headScriptUrls'),
      bodyScriptUrls: self::mergeLists(self::resolveConfigList($documentConfig, 'bodyScriptUrls', self::DEFAULT_BODY_SCRIPT_URLS), $props, 'bodyScriptUrls'),
      base: array_key_exists('base', $props) ? (string)$props['base'] : (string)($documentConfig['base'] ?? self::DEFAULT_BASE),
      lang: array_key_exists('lang', $props) ? (string)$props['lang'] : (string)($documentConfig['lang'] ?? self::DEFAULT_LANG),
      favicon: self::resolveFavicon($documentConfig, $props),
    );
  }

  /**
   * Renders non-meta head tags such as links, scripts, and favicon tags.
   */
  public function generateHeadAssetTags(): string
  {
    return $this->generateFaviconLinkTag()
      . $this->generateStyleTag()
      . $this->generateLinkTags()
      . $this->generateHeadScriptTags()
      . $this->generateHeadScriptImportTags()
      . $this->generateBaseTag();
  }

  /**
   * @return string
   */
  private function generateStyleTag(): string
  {
    $html = '';

    if ($this->styles === []) {
      return $html;
    }

    $html .= $this->getIndent(2) . "<style>" . PHP_EOL;

    foreach ($this->styles as $style) {
      $html .= $this->getIndent(3) . $style . PHP_EOL;
    }

    $html .= $this->getIndent(2) . "</style>" . PHP_EOL;

    return $html;
  }

  /**
   * @return string
   */
  private function generateLinkTags(): string
  {
    $html = '';

    foreach ($this->links as $link) {
      $rel = 'stylesheet';
      $href = $link;

      if (is_array($link)) {
        [$rel, $href] = match (count($link)) {
          0 => ['', ''],
          1 => ['', $link[0]],
          default => $link,
        };
      }

      $html .= $this->getIndent(2) . "<link rel='$rel' href='$href' />" . PHP_EOL;
    }

    return $html;
  }

  /**
   * @return string
   */
  private function generateTitleTag(): string
  {
    return $this->getIndent(2) . "<title>" . ($this->title ?: self::DEFAULT_TITLE) . "</title>" . PHP_EOL;
  }

  /**
   * @return string
   */
  private function generateMetaTags(): string
  {
    return (string)$this->meta;
  }

  /**
   * @return string
   */
  private function generateHeadScriptTags(): string
  {
    $html = '';

    if ($this->headScripts === []) {
      return $html;
    }

    $html .= $this->getIndent(2) . "<script>";

    foreach ($this->headScripts as $code) {
      $html .= $this->getIndent(3) . $code . PHP_EOL;
    }

    $html .= $this->getIndent(2) . "</script>" . PHP_EOL;

    return $html;
  }

  /**
   * @return string
   */
  private function generateHeadScriptImportTags(): string
  {
    $html = '';

    foreach ($this->headScriptUrls as $url) {
      $html .= $this->getIndent(2) . "<script defer src='$url'></script>" . PHP_EOL;
    }

    return $html;
  }

  /**
   * @return string
   */
  private function generateBaseTag(): string
  {
    return $this->getIndent(2) . "<base href='$this->base' />";
  }

  /**
   * @return string
   */
  public function generateBodyScriptTags(): string
  {
    $html = '';

    if ($this->bodyScripts === []) {
      return $html;
    }

    $html .= "<script>";

    foreach ($this->bodyScripts as $code) {
      $html .= $code . PHP_EOL;
    }

    $html .= "</script>";

    return $html;
  }

  /**
   * @return string
   */
  public function generateBodyScriptImportTags(): string
  {
    $html = '';

    foreach ($this->bodyScriptUrls as $url) {
      $html .= "<script src='$url' defer></script>";
    }

    return $html;
  }

  /**
   * @param int $level
   * @return string
   */
  private function getIndent(int $level = 1): string
  {
    return str_repeat(self::PADDING, $level);
  }

  /**
   * @return string
   */
  private function generateFaviconLinkTag(): string
  {
    [$href, $type] = $this->favicon;

    if (!$href) {
      $href = self::DEFAULT_FAVICON[0];
    }

    if (!$type) {
      $type = self::DEFAULT_FAVICON[1];
    }

    return $this->getIndent(2) . "<link rel='shortcut icon' href='$href' type='$type' />" . PHP_EOL;
  }

  /**
   * @return array<string, mixed>
   */
  private static function getDocumentConfig(): array
  {
    $config = FrameworkConfig::get('app');

    return is_array($config) ? $config : [];
  }

  /**
   * @param array<string, mixed> $documentConfig
   * @param array<string, mixed> $props
   * @return array<string, mixed>|ViewMeta
   */
  private static function resolveMeta(array $documentConfig, array $props): array|ViewMeta
  {
    $configMeta = is_array($documentConfig['meta'] ?? null)
      ? $documentConfig['meta']
      : [];

    if (!array_key_exists('meta', $props)) {
      return $configMeta !== [] ? $configMeta : new ViewMeta();
    }

    if (!is_array($props['meta'])) {
      return $configMeta !== [] ? $configMeta : new ViewMeta();
    }

    return array_replace_recursive($configMeta, $props['meta']);
  }

  /**
   * @param array<string, mixed> $documentConfig
   * @param array<string, mixed> $props
   * @return array<int, string>
   */
  private static function resolveFavicon(array $documentConfig, array $props): array
  {
    if (array_key_exists('favicon', $props) && is_array($props['favicon'])) {
      return $props['favicon'];
    }

    if (is_array($documentConfig['favicon'] ?? null)) {
      return $documentConfig['favicon'];
    }

    return self::DEFAULT_FAVICON;
  }

  /**
   * @param array<string, mixed> $documentConfig
   * @param array<int, mixed> $default
   * @return array<int, mixed>
   */
  private static function resolveConfigList(array $documentConfig, string $key, array $default): array
  {
    return is_array($documentConfig[$key] ?? null)
      ? $documentConfig[$key]
      : $default;
  }

  /**
   * @param array<int, mixed> $defaults
   * @param array<string, mixed> $props
   * @return array<int, mixed>
   */
  private static function mergeLists(array $defaults, array $props, string $key): array
  {
    if (!array_key_exists($key, $props) || !is_array($props[$key])) {
      return $defaults;
    }

    return array_values(array_merge($defaults, $props[$key]));
  }
}
