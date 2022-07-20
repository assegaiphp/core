<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * The HTTP POST method sends data to the server. The type of the body of
 * the request is indicated by the Content-Type header.
 *
 * The difference between PUT and POST is that PUT is idempotent: calling
 * it once or several times successively has the same effect (that is no
 * side effect), where successive identical POST may have additional
 * effects, like passing an order several times.
 *
 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods/POST
 */
#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION)]
class Post
{
  public function __construct(
    public readonly string $path = ''
  )
  {
  }
}