<?php

namespace Assegai\Core\Http\Responses\Enumerations;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Rendering\View;
use ReflectionClass;

/**
 * Enumerates the types of responders.
 *
 * @package Assegai\Core\Http\Responses\Enumerations
 */
enum ResponderType
{
  case ARRAY;
  case CLOSURE;
  case COMPONENT;
  case JSON;
  case VIEW;
  case OBJECT;

  /**
   * Returns the ResponderType from the given response.
   *
   * @param mixed $response The response to create the ResponderType from.
   * @return ResponderType|null The ResponderType or null if the response is not recognized.
   */
  public static function fromResponse(mixed $response): ?ResponderType
  {
    if ($response instanceof Response) {
      $responseBody = $response->getBody();

      if (is_object($responseBody)) {
        $responseBodyClassName = get_class($responseBody);

        if (
          str_contains($responseBodyClassName, 'DeleteResult') ||
          str_contains($responseBodyClassName, 'FindResult') ||
          str_contains($responseBodyClassName, 'InsertResult') ||
          str_contains($responseBodyClassName, 'UpdateResult')
        ) {
          return ResponderType::JSON;
        }

        if ($responseBody instanceof View) {
          return ResponderType::VIEW;
        }

        if (self::isComponent($responseBody)) {
          return ResponderType::COMPONENT;
        }
      }

      if (is_array($responseBody)) {
        return ResponderType::ARRAY;
      }

      if (is_scalar($responseBody)) {
        return null;
      }
    }

    if (is_countable($response)) {
      if (is_array($response) && isset($response[0]) && is_scalar($response[0])) {
        return ResponderType::ARRAY;
      }
    }

    if (is_object($response)) {
      return ResponderType::OBJECT;
    }

    if (is_callable($response) || $response instanceof \Closure) {
      return ResponderType::CLOSURE;
    }

    return null;
  }

  /**
   * Check if the given object is a component.
   *
   * @param object $object The object to check.
   * @return bool True if the object is a component, false otherwise.
   */
  private static function isComponent(object $object): bool
  {
    $objectReflection = new ReflectionClass($object);
    $componentAttribute = $objectReflection->getAttributes(Component::class);
    return !empty($componentAttribute);
  }
}
