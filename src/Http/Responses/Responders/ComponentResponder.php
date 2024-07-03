<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Components\AssegaiComponent;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Http\InternalServerErrorException;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Rendering\Interfaces\TemplateEngineInterface;
use ReflectionClass;
use ReflectionException;

/**
 * The ComponentResponder class. This class is used to render components.
 *
 * @package Assegai\Core\Http\Responses\Responders
 */
class ComponentResponder implements ResponderInterface
{
  /**
   * Constructs a ComponentResponder.
   *
   * @param TemplateEngineInterface $templateEngine The template engine.
   */
  public function __construct(protected TemplateEngineInterface $templateEngine)
  {
  }

  /**
   * @inheritDoc
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): never
  {
    if ($response instanceof Response) {
      $response->setContentType(ContentType::HTML);
      $responseBody = $response->getBody();

      if ($this->isComponent($responseBody)) {
        /** @var ComponentInterface $responseBody */
        exit($this
          ->templateEngine
          ->setRootComponent($responseBody)
          ->render());
      }
    }

    if ($this->isComponent($response)) {
      /** @var ComponentInterface $response */
      exit($this
        ->templateEngine
        ->setRootComponent($response)
        ->render());
    }

    throw new InternalServerErrorException("Invalid response.");
  }
  /**
   * Check if the given object is a component.
   *
   * @param mixed $object
   * @return bool
   * @throws ReflectionException
   */
  private function isComponent(mixed $object): bool
  {
    if (! is_object($object)) {
      return false;
    }

    $reflection = new ReflectionClass($object);
    $componentAttributes = $reflection->getAttributes(Component::class);

    if (empty($componentAttributes)) {
      return false;
    }

    if (! $object instanceof AssegaiComponent) {
      return false;
    }

    return true;
  }
}