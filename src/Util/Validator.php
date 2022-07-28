<?php

namespace Assegai\Core\Util;

use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Head;
use Assegai\Core\Attributes\Http\Options;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Put;
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