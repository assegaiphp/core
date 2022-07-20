<?php

namespace Assegai\Core\Responses;

use stdClass;

class Response
{
  protected static ?Response $instance = null;
  protected string|array|stdClass $body;

  private final function __construct()
  {
  }

  public static function getInstance(): Response
  {
    if (!self::$instance)
    {
      self::$instance = new Response();
    }

    return self::$instance;
  }

  public function status(HttpStatusCode|int $status): Response
  {
    return $this;
  }

  public function json(array|stdClass|null $body = null): Response
  {
    header('Content-Type: application/json');

    if ($body)
    {
      $this->body = $body;
    }

    return $this;
  }

  public function html(): Response
  {
    header('Content-Type: text/html');
    return $this;
  }

  public function plain(): Response
  {
    header('Content-Type: text/plain');
    return $this;
  }

  public function value(): string
  {
    return json_encode($this->body);
  }
}