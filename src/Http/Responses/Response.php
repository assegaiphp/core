<?php

namespace Assegai\Core\Http\Responses;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Injector;
use JetBrains\PhpStorm\ArrayShape;
use stdClass;
use Stringable;

#[Injectable]
class Response implements Stringable, ResponseInterface
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
   * @var bool Whether JSON responses should be wrapped in ApiResponse.
   */
  protected bool $wrapJsonBody = true;
  /**
   * @var HttpStatusCode|int The status code.
   */
  protected HttpStatusCode|int $status;
  /**
   * @var int The status resolution priority.
   */
  protected int $statusPriority = 0;
  /**
   * @var array<string, array{name: string, value: string, replace: bool, status_code: int|null}>
   */
  protected array $headers = [];
  /**
   * @var string|null The redirect target, if any.
   */
  protected ?string $redirectUrl = null;
  /**
   * @var int The redirect resolution priority.
   */
  protected int $redirectPriority = 0;

  /**
   * Constructs a Response.
   */
  private final function __construct()
  {
    $this->reset();
  }

  /**
   * @return Response
   */
  public static function getInstance(): Response
  {
    if (!self::$instance)
    {
      self::$instance = self::create();
    }

    return self::$instance;
  }

  /**
   * Returns the response bound to the active request scope when available.
   *
   * @return Response
   */
  public static function current(): Response
  {
    $injector = Injector::getInstance();
    $response = $injector->get(self::class);

    if ($response instanceof self) {
      return $response;
    }

    $response = $injector->get(ResponseInterface::class);

    if ($response instanceof self) {
      return $response;
    }

    return self::getInstance();
  }

  /**
   * Creates a fresh response object for a request cycle.
   *
   * @return Response
   */
  public static function create(): Response
  {
    return new Response();
  }

  /**
   * Replaces the current in-flight response instance.
   *
   * @param Response|null $instance
   * @return void
   */
  public static function setInstance(?Response $instance): void
  {
    self::$instance = $instance;
  }

  /**
   * Resets the response to a clean per-request state.
   *
   * @return void
   */
  public function reset(): void
  {
    $this->body = new stdClass();
    $this->contentType = ContentType::HTML;
    $this->wrapJsonBody = true;
    $this->status = HttpStatus::OK();
    $this->statusPriority = 0;
    $this->headers = [];
    $this->redirectUrl = null;
    $this->redirectPriority = 0;
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
   * @return int
   */
  public function getStatusCode(): int
  {
    return is_int($this->status) ? $this->status : $this->status->code;
  }

  /**
   * @param HttpStatusCode|int $status
   * @return void
   */
  public function setStatus(HttpStatusCode|int $status): void
  {
    $this->applyStatus($status, 30);
  }

  /**
   * Applies a status using the given priority. Higher priorities win.
   *
   * @param HttpStatusCode|int $status
   * @param int $priority
   * @return void
   */
  public function applyStatus(HttpStatusCode|int $status, int $priority = 30): void
  {
    if ($priority < $this->statusPriority) {
      return;
    }

    $this->statusPriority = $priority;
    $this->status = is_int($status) ? HttpStatus::fromInt($status) : $status;
  }

  /**
   * @param array|stdClass|null $body
   * @return $this
   */
  public function json(array|stdClass|null $body = null): Response
  {
    $this->setContentType(ContentType::JSON);
    $this->wrapJsonBody = true;

    if (!is_null($body))
    {
      $this->body = $body;
    }

    return $this;
  }

  /**
   * Configures a raw JSON response body without the framework API envelope.
   *
   * @param string|array|object $body
   * @return $this
   */
  public function jsonRaw(string|array|object $body): Response
  {
    $this->setContentType(ContentType::JSON);
    $this->wrapJsonBody = false;
    $this->body = $body;

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
   * Queues a redirect response.
   *
   * @param string $url
   * @param HttpStatusCode|int $status
   * @return $this
   */
  public function redirect(string $url, HttpStatusCode|int $status = 302): Response
  {
    return $this->applyRedirect($url, $status, 30);
  }

  /**
   * Applies a redirect using the given priority. Higher priorities win.
   *
   * @param string $url
   * @param HttpStatusCode|int $status
   * @param int $priority
   * @return $this
   */
  public function applyRedirect(string $url, HttpStatusCode|int $status = 302, int $priority = 30): Response
  {
    if ($priority < $this->redirectPriority) {
      return $this;
    }

    $this->redirectPriority = $priority;
    $this->redirectUrl = $url;
    $this->setHeader('Location', $url);
    $this->applyStatus($status, $priority);

    return $this;
  }

  /**
   * @return bool
   */
  public function isRedirect(): bool
  {
    return !is_null($this->redirectUrl);
  }

  /**
   * @return string|null
   */
  public function getRedirectUrl(): ?string
  {
    return $this->redirectUrl;
  }

  /**
   * @return string|array|object
   */
  public function getBody(): string|array|object
  {
    return $this->body;
  }

  /**
   * @return bool
   */
  public function shouldWrapJsonBody(): bool
  {
    return $this->wrapJsonBody;
  }

  /**
   * @param ContentType $contentType
   * @return void
   */
  public function setContentType(ContentType $contentType): void
  {
    $this->contentType = $contentType;
    $this->removeHeader('Content-Type');
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

  /**
   * Stores a response header for later emission.
   *
   * @param string $name
   * @param string $value
   * @param bool $replace
   * @param int|null $statusCode
   * @return void
   */
  public function setHeader(string $name, string $value, bool $replace = true, ?int $statusCode = null): void
  {
    $key = strtolower($name);

    if (!$replace) {
      $key .= '#' . count($this->headers);
    }

    $this->headers[$key] = [
      'name' => $name,
      'value' => $value,
      'replace' => $replace,
      'status_code' => $statusCode,
    ];
  }

  /**
   * Returns all queued headers.
   *
   * @return array<int, array{name: string, value: string, replace: bool, status_code: int|null}>
   */
  public function getHeaders(): array
  {
    $headers = array_values($this->headers);

    if (!$this->hasExplicitHeader('Content-Type')) {
      $headers[] = [
        'name' => 'Content-Type',
        'value' => $this->contentType->value,
        'replace' => true,
        'status_code' => null,
      ];
    }

    return $headers;
  }

  /**
   * Returns the latest value for the given header, if present.
   *
   * @param string $name
   * @return string|null
   */
  public function getHeader(string $name): ?string
  {
    $normalizedName = strtolower($name);

    foreach (array_reverse($this->getHeaders()) as $header) {
      if (strtolower($header['name']) === $normalizedName) {
        return $header['value'];
      }
    }

    return null;
  }

  /**
   * @param string $name
   * @return bool
   */
  public function hasHeader(string $name): bool
  {
    return !is_null($this->getHeader($name));
  }

  /**
   * Removes any queued entries for the given header name.
   *
   * @param string $name
   * @return void
   */
  public function removeHeader(string $name): void
  {
    $normalizedName = strtolower($name);

    foreach (array_keys($this->headers) as $key) {
      if (str_starts_with($key, $normalizedName)) {
        unset($this->headers[$key]);
      }
    }
  }

  /**
   * Emits the queued response headers to PHP's header stack.
   *
   * @return void
   */
  public function sendHeaders(): void
  {
    foreach ($this->getHeaders() as $header) {
      $headerLine = $header['name'] . ': ' . $header['value'];

      if (is_null($header['status_code'])) {
        header($headerLine, $header['replace']);
        continue;
      }

      header($headerLine, $header['replace'], $header['status_code']);
    }
  }

  /**
   * Determines whether the given header exists explicitly in the queue.
   *
   * @param string $name
   * @return bool
   */
  private function hasExplicitHeader(string $name): bool
  {
    $normalizedName = strtolower($name);

    foreach (array_keys($this->headers) as $key) {
      if (str_starts_with($key, $normalizedName)) {
        return true;
      }
    }

    return false;
  }
}
