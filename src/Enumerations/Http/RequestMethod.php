<?php

namespace Assegai\Core\Enumerations\Http;

enum RequestMethod: string
{
  case GET = "GET";
  case POST = "POST";
  case PUT = "PUT";
  case PATCH = "PATCH";
  case DELETE = "DELETE";
  case OPTIONS = "OPTIONS";
  case HEAD = "HEAD";

  static function ALL(): self
  {
    return match ($_SERVER['REQUEST_METHOD']) {
      'POST' => self::POST,
      'PUT' => self::PUT,
      'PATCH' => self::PATCH,
      'DELETE' => self::DELETE,
      'OPTIONS' => self::OPTIONS,
      'HEAD' => self::HEAD,
      default => self::GET
    };
  }
}