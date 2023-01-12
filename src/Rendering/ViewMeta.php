<?php

namespace Assegai\Core\Rendering;

/**
 *
 */
class ViewMeta
{
  const DEFAULT_PROPS = [
    'name' => 'width=device-width, initial-scale=1.0'
  ];
  /**
   *
   */
  public function __construct(
    public readonly array $httpEquiv = [],
    public readonly array $props = [],
  )
  {
  }

  /**
   * @return string
   */
  public function __toString(): string
  {
    $html = $this->generateCharsetTag();
    $html .= $this->generateHttpEquivTags();
    $html .= $this->generateAttributeTags();

    return $html;
  }

  /**
   * @param array $meta
   * @return static
   */
  public static function fromArray(array $meta): self
  {
    return new self(httpEquiv: $meta['httpEquiv'] ?? [], props: $meta['props'] ?? []);
  }

  /**
   * @return string
   */
  private function generateCharsetTag(): string
  {
    return "<meta charset='UTF-8' />" . PHP_EOL;
  }

  /**
   * @return string
   */
  private function generateHttpEquivTags(): string
  {
    $html = '';
    foreach ($this->httpEquiv as $name => $content)
    {
      $html .= "<meta http-equiv='$name' content='$content' />" . PHP_EOL;
    }
    return $html;
  }

  /**
   * @return string
   */
  private function generateAttributeTags(): string
  {    $html = '';
    foreach ($this->props as $name => $content)
    {
      $html .= "<meta name='$name' content='$content'>" . PHP_EOL;
    }
    return $html;
  }
}