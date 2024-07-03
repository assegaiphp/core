<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Http\Responses\Enumerations\ResponderType;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Rendering\Engines\ViewEngine;
use Assegai\Core\Rendering\Interfaces\TemplateEngineInterface;

class ResponderFactory
{
  /**
   * Create a responder based on the given type.
   *
   * @param ResponderType|null $responder The type of responder to create.
   * @param array<string, mixed> $data The data to pass to the responder.
   * @return ResponderInterface
   */
  public static function createResponder(?ResponderType $responder = null, array $data = []): ResponderInterface
  {
    $viewEngine = $data['viewEngine'] ?? ViewEngine::getInstance();
    /**
     * @var TemplateEngineInterface $templateEngine The template engine.
     */
    $templateEngine = $data['templateEngine'] ?? new DefaultTemplateEngine();

    return match ($responder) {
      ResponderType::ARRAY,
      ResponderType::OBJECT,
      ResponderType::JSON       => new JsonResponder(),
      ResponderType::CLOSURE    => new ClosureResponder(),
      ResponderType::COMPONENT  => new ComponentResponder($templateEngine),
      ResponderType::VIEW       => new ViewResponder($viewEngine),
      default                   => new DefaultResponder()
    };
  }
}