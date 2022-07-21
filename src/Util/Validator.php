<?php

namespace Assegai\Core\Util;

use Assegai\Core\Attributes\Delete;
use Assegai\Core\Attributes\Get;
use Assegai\Core\Attributes\Head;
use Assegai\Core\Attributes\Options;
use Assegai\Core\Attributes\Patch;
use Assegai\Core\Attributes\Post;
use Assegai\Core\Attributes\Put;
use ReflectionAttribute;

class Validator
{
  private final function __construct()
  {}

  public static function isValidRequestMapperAttribute(ReflectionAttribute $attribute): bool
  {
    $requestMapperClasses = [
      Delete::class,
      Get::class,
      Head::class,
      Options::class,
      Patch::class,
      Post::class,
      Put::class,
    ];

    return in_array($attribute->getName(), $requestMapperClasses);
  }
}