<?php

namespace Assegai\Core\Rendering;

use Assegai\Core\Enumerations\ViewType;

/**
 *
 */
class ViewProperties
{
  /**
   *
   */
  const DEFAULT_TYPE = ViewType::HTML;
  /**
   *
   */
  const DEFAULT_TITLE = 'AssegaiPHP';
  /**
   *
   */
  const DEFAULT_STYLES = [];
  /**
   *
   */
  const DEFAULT_LINKS = ['/css/style.css'];
  /**
   *
   */
  const DEFAULT_HEAD_SCRIPTS = [];
  /**
   *
   */
  const DEFAULT_BODY_SCRIPTS = [];
  /**
   *
   */
  const DEFAULT_HEAD_SCRIPT_URLS = ['/js/main.js'];
  /**
   *
   */
  const DEFAULT_BODY_SCRIPT_URLS = [];
  /**
   *
   */
  const DEFAULT_BASE = '/';
  /**
   *
   */
  const DEFAULT_LANG = 'en';
  /**
   *
   */
  private const PADDING = '  ';

  /**
   * @var ViewMeta
   */
  public readonly ViewMeta $meta;

  /**
   * @param ViewType $type
   * @param string $title
   * @param array $styles
   * @param ViewMeta|array $meta
   * @param array $links
   * @param array $headScripts
   * @param array $bodyScripts
   * @param array $headScriptUrls
   * @param array $bodyScriptUrls
   * @param string $base
   * @param string $lang
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
    if ($this->styles)
    {
      $html .= $this->getIndent(2) . "<style>" . PHP_EOL;
      foreach ($this->styles as $style)
      {
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
      if (is_array($link))
      {
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
    if ($this->headScripts)
    {
      $html .= $this->getIndent(2) . "<script>";
      foreach ($this->headScripts as $code)
      {
        $html .= $this->getIndent(3) . $code . PHP_EOL;
      }
      $html .= $this->getIndent(2) . "<script>" . PHP_EOL;
    }

    return $html;
  }

  /**
   * @return string
   */
  private function generateHeadScriptImportTags(): string
  {
    $html = '';
    foreach ($this->headScriptUrls as $url)
    {
      $html .= $this->getIndent(2) . "<script src='$url'></script>" . PHP_EOL;
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
    if ($this->bodyScripts)
    {
      $html .= "<script>";
      foreach ($this->bodyScripts as $code)
      {
        $html .= $code . PHP_EOL;
      }
      $html .= "<script>";
    }

    return $html;
  }

  /**
   * @return string
   */
  public function generateBodyScriptImportTags(): string
  {
    $html = '';
    foreach ($this->bodyScriptUrls as $url)
    {
      $html .= "<script src='$url'></script>";
    }
    return $html;
  }

  private function getIndent(int $level = 1): string
  {
    return str_repeat(self::PADDING, $level);
  }
}