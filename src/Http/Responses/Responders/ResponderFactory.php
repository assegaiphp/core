<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Http\Responses\Enumerations\ResponderType;
use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
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
    /** @var ResponseEmitterInterface $emitter */
    $emitter = $data['emitter'] ?? new PhpResponseEmitter();
    /** @var RequestInterface|null $request */
    $request = $data['request'] ?? null;
    /** @var ResponseInterface|null $response */
    $response = $data['response'] ?? null;
    /** @var ResponderInterface|null $rootResponder */
    $rootResponder = $data['responder'] ?? null;

    return match ($responder) {
      ResponderType::ARRAY,
      ResponderType::OBJECT,
      ResponderType::JSON       => new JsonResponder($emitter, $request, $response),
      ResponderType::CLOSURE    => new ClosureResponder($emitter, $rootResponder),
      ResponderType::COMPONENT  => new ComponentResponder($templateEngine, $emitter, $response),
      ResponderType::VIEW       => new ViewResponder($viewEngine, $emitter, $response),
      default                   => new DefaultResponder($emitter, $response)
    };
  }
}
