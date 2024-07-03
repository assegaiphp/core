<?php

namespace Assegai\Core\Http\Responses;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use JetBrains\PhpStorm\ArrayShape;
use stdClass;
use Stringable;

#[Injectable]
class Response implements Stringable
{
  /**
   * @var Response|null The Response instance.
   */
  protected static ?Response $instance = null;
  /**
   * @var string|array|object The response body.
   */
  protected string|array|object $body;
  /**
   * @var ContentType The content type.
   */
  protected ContentType $contentType = ContentType::JSON;
  /**
   * @var HttpStatusCode|int The status code.
   */
  protected HttpStatusCode|int $status;

  /**
   * Constructs a Response.
   */
  private final function __construct()
  {
    $this->body = new stdClass();
    $this->status = http_response_code();
    $this->setContentType(ContentType::HTML);
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
   * @inheritDoc
   */
  public function __toString(): string
  {
    return $this->toJson();
  }

  /**
   * @return array|array[]|object[]|stdClass[]|string[]
   */
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
  public function setContentType(ContentType $contentType): void
  {
    $this->contentType = $contentType;
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