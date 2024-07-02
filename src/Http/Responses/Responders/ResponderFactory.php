<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Http\Responses\Enumerations\ResponderType;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;

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
    return match ($responder) {
      ResponderType::ARRAY => new ArrayResponder(),
      ResponderType::CLOSURE => new ClosureResponder(),
      ResponderType::COMPONENT => new ComponentResponder(),
      ResponderType::JSON => new JsonResponder(),
      ResponderType::OBJECT => new ObjectResponder(),
      ResponderType::VIEW => new ViewResponder(),
      default => new DefaultResponder()
    };
  }
}