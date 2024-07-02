<?php

namespace Assegai\Core\Http\Responses;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Rendering\Engines\ViewEngine;
use Assegai\Core\Rendering\Interfaces\TemplateEngineInterface;
use Assegai\Core\Rendering\View;
use Assegai\Orm\Queries\QueryBuilder\Results\DeleteResult;
use Assegai\Orm\Queries\QueryBuilder\Results\InsertResult;
use Assegai\Orm\Queries\QueryBuilder\Results\UpdateResult;
use Closure;
use ReflectionClass;
use ReflectionException;

/**
 * Contains useful methods for managing the Response object.
 */
class Responder
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
   * Constructs a Responder.
   */
  private final function __construct()
  {
    $this->viewEngine = ViewEngine::getInstance();
    $this->templateEngine = new DefaultTemplateEngine();
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
   * Send a response to the client and exit the script.
   *
   * @param mixed $response The response to send.
   * @param HttpStatusCode|int|null $code The response code to send.
   * @return never
   * @throws ReflectionException
   * @throws RenderingException
   */
  public function respond(mixed $response, HttpStatusCode|int|null $code = null): never
  {
    if ($code) {
      $this->setResponseCode($code);
    }

    if ($response instanceof Response) {
      $this->setResponseCode($response->getStatus());

      $responseBody = $response->getBody();

      if ($responseBody instanceof View) {
        $this->viewEngine->load($responseBody)->render();
      }
    }

    $responseString = match(true) {
      is_object($response) => match(true) {
        $this->isComponent($response) => $this->templateEngine->setRootComponent($response)->render(),
        is_callable($response),
        $response instanceof Closure => $response(),
        default => json_encode($response) ?: $response
      },
      is_countable($response) => (is_array($response) && isset($response[0]) && is_scalar($response[0]) ?  : new ApiResponse(data: $response) ),
      ($response instanceof Response) => match($response->getContentType()) {
        ContentType::JSON => match (true) {
          ($response->getBody() instanceof DeleteResult) => strval($response->getBody()->affected),
          ($response->getBody() instanceof InsertResult),
          ($response->getBody() instanceof UpdateResult) => json_encode($response->getBody()->getData()),
          default => new ApiResponse(data: $response->getBody())
        },
        default => $response->getBody()
      }
    };

    exit($responseString);
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

  /**
   * Check if the response is a component.
   *
   * @param mixed $response
   * @return bool
   * @throws ReflectionException
   */
  private function isComponent(mixed $response): bool
  {

    $reflection = new ReflectionClass($response);
    $componentAttributes = $reflection->getAttributes(Component::class);

    if (empty($componentAttributes)) {
      return false;
    }

    return true;
  }
}