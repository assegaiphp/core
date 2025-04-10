<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Enumerations\ResponderType;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Rendering\Engines\ViewEngine;
use Assegai\Core\Rendering\Interfaces\TemplateEngineInterface;

/**
 * Contains useful methods for managing the Response object.
 */
class Responder implements ResponderInterface
{
  /**
   * @var Responder|null The Responder instance.
   */
  private static ?Responder $instance = null;
  /**
   * @var ViewEngine The ViewEngine instance.
   */
  private ViewEngine $viewEngine;
  /**
   * @var TemplateEngineInterface The TemplateEngine instance.
   */
  private TemplateEngineInterface $templateEngine;
  /**
   * @var ResponderInterface The context.
   */
  protected ResponderInterface $context;
  /**
   * @var ResponderType The ResponderType.
   */
  protected ResponderType $responderType;

  /**
   * Constructs a Responder.
   */
  private final function __construct()
  {
    $this->viewEngine = ViewEngine::getInstance();
    $this->templateEngine = new DefaultTemplateEngine();
    $this->context = ResponderFactory::createResponder(data: ['templateEngine' => $this->templateEngine]);
  }

  /**
   * Get an instance of Responder.
   *
   * @return Responder The Responder instance.
   */
  public static function getInstance(): Responder
  {
    if (!self::$instance) {
      self::$instance = new Responder();
    }

    return self::$instance;
  }

  /**
   * Set the TemplateEngine instance.
   *
   * @param TemplateEngineInterface $templateEngine The TemplateEngine instance.
   * @return void
   */
  public function setTemplateEngine(TemplateEngineInterface $templateEngine): void
  {
    $this->templateEngine = $templateEngine;
  }

  /**
   * Get the TemplateEngine instance.
   *
   * @return TemplateEngineInterface The TemplateEngine instance.
   */
  public function getTemplateEngine(): TemplateEngineInterface
  {
    return $this->templateEngine;
  }

  /**
   * Get Request instance
   *
   * @return Request The Request instance.
   */
  public function getRequest(): Request
  {
    return Request::getInstance();
  }

  /**
   * @inheritDoc
   */
  public function respond(mixed $response, HttpStatusCode|int|null $code = null): never
  {
    if ($code) {
      $this->setResponseCode($code);
    }

    $this->context =
      ResponderFactory::createResponder(
        ResponderType::fromResponse($response),
        [
          'viewEngine' => $this->viewEngine,
          'templateEngine' => $this->templateEngine
        ]
      );

    $this->context->respond($response, $code);
  }

  /**
   * Set the response code.
   *
   * @param HttpStatusCode|int|null $code The code to set.
   * @return void
   */
  public function setResponseCode(HttpStatusCode|int|null $code = 200): void
  {
    $codeObject = $code;

    if (!$codeObject) {
      return;
    }

    if (is_int($code)) {
      $codeObject = HttpStatus::fromInt($code);
    }

    http_response_code($codeObject->code);
  }
}