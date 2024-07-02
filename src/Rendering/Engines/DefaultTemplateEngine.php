<?php

namespace Assegai\Core\Rendering\Engines;

/**
 * The default template engine.
 *
 * @package Assegai\Core\Rendering\Engines
 */
class DefaultTemplateEngine extends TemplateEngine
{
  /**
   * @inheritDoc
   */
  public function render(): string
  {
    return $this->rootComponent->render();
  }
}