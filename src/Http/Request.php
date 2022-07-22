<?php

namespace Assegai\Core\Http;

//use Assegai\Lib\Authentication\JWT\JWTToken;
use Assegai\Core\App;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config;
use Assegai\Core\Enumerations\Http\RequestMethod;
use JetBrains\PhpStorm\ArrayShape;
use stdClass;

/**
 * The **Request** class represents the HTTP request and has properties for
 * the request query string, parameters, HTTP headers, and body
 */
#[Injectable]
class Request
{
  protected mixed $body = null;
  protected array $allHeaders = [];
  protected ?App $app = null;
  protected RequestMethod $requestMethod;
  protected ?string $scheme;
  protected ?string $host;
  protected string $path;
  protected ?Query $query;
  protected string $uri;
  protected array $params = [];

  protected static ?Request $instance = null;

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
    $this->query = new Query();

    $this->requestMethod = match ($_SERVER['REQUEST_METHOD']) {
      'POST' => RequestMethod::POST,
      'PUT' => RequestMethod::PUT,
      'PATCH' => RequestMethod::PATCH,
      'DELETE' => RequestMethod::DELETE,
      'HEAD' => RequestMethod::HEAD,
      'OPTIONS' => RequestMethod::OPTIONS,
       default => RequestMethod::GET
    };
    $this->path = str_replace('/^\//', '', $this->path);

    $this->body = match ($this->getMethod()) {
      RequestMethod::GET      => $_GET,
      RequestMethod::POST     => !empty($_POST) ? $_POST : ( !empty($_FILES) ? $_FILES : file_get_contents('php://input') ),
      RequestMethod::PUT,
      RequestMethod::PATCH    => file_get_contents('php://input'),
      RequestMethod::DELETE   => !empty($_GET) ? $_GET : file_get_contents('php://input'),
      RequestMethod::HEAD,
      RequestMethod::OPTIONS  => NULL,
    };

    if (isset($this->body['path']))
    {
      unset($this->body['path']);
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
   * @return App
   */
  public function getApp(): App
  {
    return $this->app;
  }

  /**
   * @param App $app
   * @return void
   */
  public function setApp(App $app): void
  {
    $this->app = $app;
  }

  /**
   * @return array
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
   * @return mixed
   */
  public function getBody(): mixed
  {
    return $this->body;
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
   * @return Query
   */
  public function getQuery(): Query
  {
    if (!$this->query)
    {
      $this->query = new Query();
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
//      return (object) JWTToken::parse(tokenString: $tokenString, returnArray: true);
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
    $pattern = str_replace('/', '\/', $pattern);
    $params = [];
    if (preg_match_all("/$pattern/", $path, $matches))
    {
      if (count($matches) > 1)
      {
        $params = $matches[1];
      }
    }

    foreach ($params as $key => $param)
    {
      $this->params[$key] = $param;
    }
  }
}

