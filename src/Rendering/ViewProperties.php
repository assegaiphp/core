<?php

namespace Assegai\Core\Rendering;

use Assegai\Core\Enumerations\ViewType;

/**
 *
 */
class ViewProperties
{
  /**
   * The default view type.
   */
  const ViewType DEFAULT_TYPE = ViewType::HTML;
  /**
   * The default title.
   */
  const string DEFAULT_TITLE = 'AssegaiPHP';
  /**
   * The default styles.
   */
  const array DEFAULT_STYLES = [];
  /**
   *
   */
  const array DEFAULT_LINKS = ['/css/style.css'];
  /**
   * The default head scripts.
   */
  const array DEFAULT_HEAD_SCRIPTS = [];
  /**
   * The default body scripts.
   */
  const array DEFAULT_BODY_SCRIPTS = [];
  /**
   * The default head script URLs.
   */
  const array DEFAULT_HEAD_SCRIPT_URLS = ['/js/main.js'];
  /**
   * The default body script URLs.
   */
  const array DEFAULT_BODY_SCRIPT_URLS = [];
  /**
   * The default base.
   */
  const string DEFAULT_BASE = '/';
  /**
   * The default language.
   */
  const string DEFAULT_LANG = 'en';
  /**
   * The default favicon.
   */
  private const string PADDING = '  ';

  /**
   * @var ViewMeta
   */
  public readonly ViewMeta $meta;

  /**
   * Constructs a ViewProperties object.
   *
   * @param ViewType $type The view type.
   * @param string $title The title.
   * @param array $styles The styles.
   * @param ViewMeta|array $meta The meta tags.
   * @param array $links The links to stylesheets.
   * @param array $headScripts The javascript code to be included in the head.
   * @param array $bodyScripts The javascript code to be included in the body.
   * @param array $headScriptUrls The URLs of the javascript files to be included in the head.
   * @param array $bodyScriptUrls The URLs of the javascript files to be included in the body.
   * @param string $base The base URL.
   * @param string $lang The language.
   * @param array $favicon The favicon URL and type.
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
    public readonly array $favicon = ['favicon.ico', 'image/x-icon']
  )
  {
    $this->meta = is_array($meta) ? ViewMeta::fromArray($meta) : $meta;
  }

  /**
   * @return string
   */
  public function __toString(): string
  {
    $html = $this->generateMetaTags();
    $html .= $this->generateTitleTag();
    $html .= $this->generateFaviconLinkTag();
    $html .= $this->generateStyleTag();
    $html .= $this->generateLinkTags();
    $html .= $this->generateHeadScriptTags();
    $html .= $this->generateHeadScriptImportTags();
    $html .= $this->generateBaseTag();
    return $html;
  }

  /**
   * @param array $props
   * @return static
   */
  public static function fromArray(array $props): self
  {
    return new self(
      type: $props['type'] ?? self::DEFAULT_TYPE,
      title: $props['title'] ?? self::DEFAULT_TITLE,
      styles: $props['styles'] ?? self::DEFAULT_STYLES,
      meta: $props['meta'] ?? new ViewMeta(),
      links: $props['links'] ?? self::DEFAULT_LINKS,
      headScripts: $props['headScripts'] ?? self::DEFAULT_HEAD_SCRIPTS,
      bodyScripts: $props['bodyScripts'] ?? self::DEFAULT_BODY_SCRIPTS,
      headScriptUrls: $props['headScriptUrls'] ?? self::DEFAULT_HEAD_SCRIPT_URLS,
      bodyScriptUrls: $props['bodyScriptUrls'] ?? self::DEFAULT_BODY_SCRIPT_URLS,
      base: $props['base'] ?? self::DEFAULT_BASE,
      lang: $props['lang'] ?? self::DEFAULT_LANG,
    );
  }

  /**
   * @return string
   */
  private function generateStyleTag(): string
  {
    $html = '';
    if ($this->styles) {
      $html .= $this->getIndent(2) . "<style>" . PHP_EOL;
      foreach ($this->styles as $style) {
        $html .= $this->getIndent(3) . $style . PHP_EOL;
      }
      $html .= $this->getIndent(2) . "</style>" . PHP_EOL;
    }

    return $html;
  }

  /**
   * @return string
   */
  private function generateLinkTags(): string
  {
    $html = '';
    foreach ($this->links as $link)
    {
      $rel = 'stylesheet';
      $href = $link;
      if (is_array($link)) {
        list($rel, $href) = match(count($link)) {
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
    return $this->getIndent(2) . "<title>" . ($this->title ?? 'AssegaiPHP') . "</title>" . PHP_EOL;
  }

  /**
   * @return string
   */
  private function generateMetaTags(): string
  {
    return $this->meta;
  }

  /**
   * @return string
   */
  private function generateHeadScriptTags(): string
  {
    $html = '';
    if ($this->headScripts) {

      $html .= $this->getIndent(2) . "<script>";

      foreach ($this->headScripts as $code) {
        $html .= $this->getIndent(3) . $code . PHP_EOL;
      }

      $html .= $this->getIndent(2) . "</script>" . PHP_EOL;
    }

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

    if ($this->bodyScripts) {
      $html .= "<script>";

      foreach ($this->bodyScripts as $code) {
        $html .= $code . PHP_EOL;
      }

      $html .= "</script>";
    }

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
      $href = 'favicon.ico';
    }

    if (!$type) {
      $type = 'image/x-icon';
    }

    return $this->getIndent(2) . "<link rel='shortcut icon' href='$href' type='$type' />" . PHP_EOL;
  }
}