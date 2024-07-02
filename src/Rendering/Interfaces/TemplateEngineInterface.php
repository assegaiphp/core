<?php

namespace Assegai\Core\Rendering\Interfaces;

use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Rendering\Engines\TemplateEngine;

/**
 * Interface TemplateEngineInterface. This interface is for the template engine.
 *
 * @package Assegai\Core\Rendering\Interfaces
 */
interface TemplateEngineInterface extends RendererInterface
{
  /**
   * Sets the template to render.
   *
   * @param string $template The template to render.
   * @return TemplateEngineInterface
   */
  public function setTemplate(string $template): self;

  /**
   * Sets the data to render.
   *
   * @param array $data The data to render.
   * @return TemplateEngineInterface
   */
  public function setData(array $data): self;

  /**
   * Sets the content type.
   *
   * @param ContentType $contentType The content type.
   * @return TemplateEngineInterface
   */
  public function setContentType(ContentType $contentType): self;

  /**
   * @param ComponentInterface $rootComponent
   * @return TemplateEngine
   * @return static
   * @throws RenderingException
   */
  public function setRootComponent(ComponentInterface $rootComponent): self;
}