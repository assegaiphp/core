<?php

namespace Assegai\Core\Http\Requests;

//use Assegai\Lib\Authentication\JWT\JWTToken;
use Assegai\Core\App;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotImplementedException;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Forms\Enumerations\HttpMethod;
use Assegai\Forms\Exceptions\FormException;
use Assegai\Forms\Exceptions\InvalidFormException;
use Assegai\Forms\Form;
use JetBrains\PhpStorm\ArrayShape;
use stdClass;

/**
 * The **Request** class represents the HTTP request and has properties for
 * the request query string, parameters, HTTP headers, and body
 */
#[Injectable]
class Request
{
  /**
   * @var null|stdClass
   */
  protected null|stdClass $body = null;
  /**
   * @var array
   */
  protected array $allHeaders = [];
  /**
   * @var null|AppInterface
   */
  protected ?AppInterface $app = null;
  /**
   * @var RequestMethod The request method.
   */
  protected RequestMethod $requestMethod;
  /**
   * @var null|string The request scheme.
   */
  protected ?string $scheme;
  /**
   * @var null|string The request host name.
   */
  protected ?string $host;
  /**
   * @var string The request path.
   */
  protected string $path;
  /**
   * @var null|RequestQuery
   */
  protected ?RequestQuery $query;
  /**
   * @var string The request URI.
   */
  protected string $uri;
  /**
   * @var array The request parameters.
   */
  protected array $params = [];
  /**
   * @var null|object|array The request file.
   */
  protected array|object|null $file = null;
  /**
   * @var null|Request The request instance.
   */
  protected static ?Request $instance = null;
  /**
   * @var Form The request form.
   */
  protected Form $form;
  protected ContentType $contentType;

  /**
   * Constructs a Request object.
   *
   * @throws FormException
   * @throws HttpException
   * @throws InvalidFormException
   */
  private final function __construct()
  {
    $this->uri = $_SERVER['REQUEST_URI'];
    $parsedUrl = parse_url($this->uri);

    $scheme = null;
    $host = null;
    $path = null;

    if (is_array($parsedUrl))
    {
      extract($parsedUrl);
    }

    $this->scheme = $scheme ?? ($_SERVER['REQUEST_SCHEME'] ?? 'http');
    $this->host = $host ?? ($_SERVER['REMOTE_HOST'] ?? 'localhost');
    $this->path = $path;
    $this->query = new RequestQuery();
    $this->body = new stdClass();
    $this->contentType = ContentType::tryFrom($_SERVER['CONTENT_TYPE'] ?? '') ?? ContentType::HTML;

    $this->requestMethod = match ($_SERVER['REQUEST_METHOD']) {
      'POST'    => RequestMethod::POST,
      'PUT'     => RequestMethod::PUT,
      'PATCH'   => RequestMethod::PATCH,
      'DELETE'  => RequestMethod::DELETE,
      'HEAD'    => RequestMethod::HEAD,
      'OPTIONS' => RequestMethod::OPTIONS,
       default  => RequestMethod::GET
    };
    $this->path = str_replace('/^\//', '', $this->path);

    $this->body = $this->extractRequestBody();

    if (isset($this->body->path))
    {
      unset($this->body->path);
    }

    foreach ($_SERVER as $key => $value)
    {
      if (str_starts_with($key, 'HTTP_'))
      {
        $this->allHeaders[$key] = $value;
      }
    }
  }

  /**
   * @return Request
   */
  public static function getInstance(): Request
  {
    if (!Request::$instance)
    {
      Request::$instance = new Request;
    }

    return Request::$instance;
  }

  /**
   * @return AppInterface|null The application instance.
   */
  public function getApp(): ?AppInterface
  {
    return $this->app;
  }

  /**
   * @param AppInterface|null $app
   * @return void
   */
  public function setApp(?AppInterface $app): void
  {
    $this->app = $app;
  }

  /**
   * Returns an array representation of the request.
   *
   * @return array{app: App, body: mixed, cookies: string, fresh: bool, headers: array, host_name: string, method: string, remote_ip: string, path: string, protocol: string}
   */
  #[ArrayShape([
    'app' => 'App', 'body' => 'mixed', 'cookies' => 'string', 'fresh' => 'bool',
    'headers' => 'array', 'host_name' => 'string', 'method' => 'string',
    'remote_ip' => 'string', 'path' => 'string', 'protocol' => 'string'
  ])]
  public function toArray(): array
  {
    return [
      'app'       => $this->app,
      'body'      => $this->getBody(),
      'cookies'   => $this->getCookies(),
      'fresh'     => $this->fresh(),
      'headers'   => $this->allHeaders(),
      'host_name' => $this->getHostName(),
      'method'    => $this->getMethod(),
      'remote_ip' => $this->getRemoteIp(),
      'path'       => $this->getPath(),
      'protocol'  => $this->getProtocol()
    ];
  }

  /**
   * @return string
   */
  public function toJSON(): string
  {
    return json_encode($this->toArray());
  }

  /**
   * @return string
   */
  public function __toString(): string
  {
    return $this->toJSON();
  }

  /**
   * @return array
   */
  public function __serialize(): array
  {
    return $this->toArray();
  }

  /**
   * @param string $name
   * @return string
   */
  public function header(string $name): string
  {
    $key = strtoupper($name);

    if (isset($_SERVER["HTTP_$key"]))
    {
      return $_SERVER["HTTP_$key"];
    }

    if (isset($_SERVER[$key]))
    {
      return $_SERVER[$key];
    }

    return '';
  }

  /**
   * @return array
   */
  public function allHeaders(): array
  {
    return $this->allHeaders;
  }

  /**
   * @return string
   */
  public function getUri(): string
  {
    return $this->uri;
  }

  /**
   * @return string
   */
  public function getPath(): string
  {
    return $this->path ?? $_SERVER['PATH_INFO'];
  }

  /**
   * @return string
   * @deprecated No longer used by internal code and not recommended.
   */
  public function path(): string
  {
    return $_GET['path'] ?? '/';
  }

  /**
   * @return int
   */
  public function getLimit(): int
  {
    $limit = $_GET['limit'] ?? Config::get('request')['DEFAULT_LIMIT'] ?? 10;

    return match(true) {
      is_int($limit) => $limit,
      is_numeric($limit) => intval($limit),
      default => 100
    };
  }

  /**
   * @return int
   */
  public function getSkip(): int
  {
    $skip = $_GET['skip'] ?? Config::get('request')['DEFAULT_SKIP'] ?? 0;

    return match (true) {
      is_int($skip) => $skip,
      is_numeric($skip) => intval($skip),
      default => 0
    };
  }

  /**
   * Returns the request body.
   *
   * @return stdClass|null The request body.
   */
  public function getBody(): ?stdClass
  {
    if (isset($this->body->scalar) && json_is_valid($this->body->scalar))
    {
      $this->body = json_decode($this->body->scalar);
    }
    return $this->body;
  }

  /**
   * Sets the request body.
   *
   * @param stdClass|null $body The request body.
   * @return void
   */
  public function setBody(?stdClass $body): void
  {
    if (!is_null($body))
    {
      $this->body = $body;
    }
  }

  /**
   * @param string|null $name
   * @return array|string
   */
  public function getCookies(?string $name = null): array|string
  {
    return $_COOKIE[$name] ?? $_COOKIE;
  }

  /**
   * @return bool
   */
  public function fresh(): bool
  {
    return false;
  }

  /**
   * @return string
   */
  public function getHostName(): string
  {
    return $_SERVER['HTTP_HOST'] ?? 'localhost';
  }

  /**
   * @return RequestMethod
   */
  public function getMethod(): RequestMethod
  {
    return $this->requestMethod;
  }

  /**
   * @return string
   */
  public function getRemoteIp(): string
  {
    return $_SERVER['REMOTE_ADDR'] ?? '::1';
  }

  /**
   * @return string
   */
  public function getProtocol(): string
  {
    return $this->scheme ?? $_SERVER['REQUEST_SCHEME'];
  }

  /**
   * @return array
   */
  public function getParams(): array
  {
    return $this->params ?? [];
  }

  /**
   * @return RequestQuery
   */
  public function getQuery(): RequestQuery
  {
    if (!$this->query)
    {
      $this->query = new RequestQuery();
    }

    return $this->query;
  }

  /**
   * Returns the access token provided with the request.
   *
   * @param bool $deconstruct If set to TRUE, then an object will be return on success.
   *
   * @return null|string|stdClass|false Returns the access token provided with the
   * request, null if non was set and false if the supplied token is invalid.
   * @throws NotImplementedException
   */
  public function accessToken(bool $deconstruct = false): null|string|stdClass|false
  {
    $accessToken = $this->header('Authorization');

    if (empty($accessToken))
    {
      return NULL;
    }

    if (!str_contains($accessToken, 'BEARER'))
    {
      return NULL;
    }

    $matches = [];
    if (!preg_match('/Bearer (.*)/', $accessToken, $matches))
    {
      return false;
    }

    if (count($matches) < 2)
    {
      return false;
    }

    $tokenString = $matches[1];

    if ($deconstruct)
    {
      return $this->deconstructToken($tokenString);
    }

    return $tokenString;
  }

  /**
   * @param string $path
   * @param string $pattern
   * @return void
   */
  public function extractParams(string $path, string $pattern): void
  {
    if (str_starts_with($path, '/'))
    {
      $path = substr($path, 1);
    }
    $pattern = str_replace('/', '\/', $pattern);
    $params = [];
    if (preg_match("/$pattern/", $path, $matches))
    {
      if (count($matches) > 1)
      {
        $totalMatches = count($matches);
        for ($x = 1; $x < $totalMatches; ++$x)
        {
          $params[] = $matches[$x];
        }
      }
    }

    foreach ($params as $key => $param)
    {
      $this->params[$key] = $param;
    }
  }


  /**
   * @return object|array
   */
  public function getFile(): object|array
  {
    return $this->file;
  }

  /**
   * @param mixed|null $file
   */
  public function setFile(array|object $file): void
  {
    $this->file = $file;
  }

  /**
   * Filter form data.
   *
   * @param false|string $data The data to filter.
   * @return array The filtered data.
   * @throws HttpException
   */
  private function filterFormData(false|string $data): array
  {
    if ($data === false)
    {
      throw new HttpException('Invalid request body.');
    }

    return [];
  }

  /**
   * Extracts the request body.
   *
   * @return object
   * @throws HttpException
   * @throws InvalidFormException
   * @throws FormException
   */
  private function extractRequestBody(): object
  {
    # Check if content type is form
    if ($this->contentType === ContentType::FORM_DATA || $this->contentType === ContentType::FORM_URL_ENCODED)
    {
      $this->form = new Form(method: HttpMethod::tryFrom($this->getMethod()->value));
      return (object) match(true) {
        !empty($_FILES)             => $_FILES,
        $this->form->isSubmitted()  => $this->form->getData(),
        default                     => file_get_contents('php://input')
      };
    }

    $body = match ($this->getMethod()) {
      RequestMethod::GET      => $_GET,
      RequestMethod::POST     => !empty($_POST) ? $_POST : file_get_contents('php://input'),
      RequestMethod::PUT,
      RequestMethod::PATCH    => file_get_contents('php://input'),
      RequestMethod::DELETE   => !empty($_GET) ? $_GET : file_get_contents('php://input'),
      RequestMethod::HEAD,
      RequestMethod::OPTIONS  => NULL,
    };

    if (is_string($body) && $this->contentType === ContentType::JSON)
    {
      if (!json_is_valid($body))
      {
        throw new HttpException('Invalid JSON request body.');
      }

      $body = json_decode($body);
    }

    return (object) $body;
  }

  /**
   * Deconstructs the access token.
   *
   * @param mixed $tokenString The access token to deconstruct.
   * @return object The deconstructed access token.
   * @throws NotImplementedException
   */
  private function deconstructToken(mixed $tokenString): object
  {
    // TODO: Implement deconstructToken() method.
    throw new NotImplementedException('Method not implemented.');
  }
}
