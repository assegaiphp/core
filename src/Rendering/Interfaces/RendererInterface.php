<?php

namespace Assegai\Core\Rendering\Interfaces;

/**
 * Interface RendererInterface. This interface is for the renderer.
 *
 * @package Assegai\Core\Rendering\Interfaces
 */
interface RendererInterface
{
  /**
   * Returns the rendered content.
   *
   * @return string The rendered content.
   */
  public function render(): string;
}