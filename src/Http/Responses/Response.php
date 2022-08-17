<?php

namespace Assegai\Core\Http\Responses;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use JetBrains\PhpStorm\ArrayShape;
use stdClass;

#[Injectable]
class Response
{
  protected static ?Response $instance = null;
  protected string|array|object $body;
  protected ContentType $contentType = ContentType::JSON;
  protected HttpStatusCode|int $status;

  private final function __construct()
  {
    $this->body = new stdClass();
    $this->status = http_response_code();
    $this->setContentType(ContentType::JSON);
  }

  /**
   * @return Response
   */
  public static function getInstance(): Response
  {
    if (!self::$instance)
    {
      self::$instance = new Response();
    }

    return self::$instance;
  }

  /**
   * @return string
   */
  public function __toString(): string
  {
    return $this->toJson();
  }

  #[ArrayShape(['body' => "array|\stdClass|string", 'type' => "\Assegai\Core\Enumerations\Http\ContentType"])]
  public function toArray(): array
  {
    return match($this->contentType) {
      ContentType::HTML => ['body' => $this->body],
      default => is_array($this->body) ? $this->body : ['body' => $this->body]
    };
  }

  /**
   * @return string
   */
  private function toJson(): string
  {
    return json_encode($this->toArray());
  }

  /**
   * @return int|HttpStatusCode
   */
  public function getStatus(): HttpStatusCode|int
  {
    return $this->status;
  }

  /**
   * @param HttpStatusCode|int $status
   * @return void
   */
  public function setStatus(HttpStatusCode|int $status): void
  {
    $this->status = is_int($status) ? HttpStatus::fromInt($status) : $status;
  }

  /**
   * @param array|stdClass|null $body
   * @return $this
   */
  public function json(array|stdClass|null $body = null): Response
  {
    $this->setContentType(ContentType::JSON);

    if ($body)
    {
      $this->body = json_encode($body);
    }

    return $this;
  }

  /**
   * @param string $body
   * @return $this
   */
  public function html(string $body): Response
  {
    $this->setContentType(ContentType::HTML);
    $this->body = htmlspecialchars($body, ENT_HTML5);

    return $this;
  }

  /**
   * @param string $body
   * @return $this
   */
  public function plainText(string $body): Response
  {
    $this->setContentType(ContentType::PLAIN);
    $this->body = strip_tags($body);

    return $this;
  }

  /**
   * @return string|array|object
   */
  public function getBody(): string|array|object
  {
    return $this->body;
  }

  /**
   * @param ContentType $contentType
   * @return void
   */
  private function setContentType(ContentType $contentType): void
  {
    $this->contentType = ContentType::JSON;
    header('Content-Type: ' . $this->contentType->value);
  }

  /**
   * @return ContentType
   */
  public function getContentType(): ContentType
  {
    return $this->contentType;
  }

  /**
   * @param string|array|object $body
   * @return void
   */
  public function setBody(string|array|object $body): void
  {
    $this->body = $body;
  }
}