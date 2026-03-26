<?php

namespace Assegai\Core\Runtimes;

use Assegai\Core\App;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Http\Responses\Emitters\OpenSwooleResponseEmitter;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\HttpRuntimeInterface;
use RuntimeException;

class OpenSwooleHttpRuntime implements HttpRuntimeInterface
{
  public function __construct(
    private readonly string $host = '127.0.0.1',
    private readonly int $port = 9501,
  )
  {
  }

  public function getName(): string
  {
    return 'openswoole';
  }

  public function run(AppInterface $app, callable $handler): void
  {
    if (!extension_loaded('openswoole')) {
      throw new RuntimeException('The OpenSwoole runtime requires the openswoole PHP extension.');
    }

    if (!class_exists('\\OpenSwoole\\HTTP\\Server')) {
      throw new RuntimeException('The OpenSwoole HTTP server class is not available in the current PHP runtime.');
    }

    $serverClass = '\\OpenSwoole\\HTTP\\Server';
    /** @var object $server */
    $server = new $serverClass($this->host, $this->port);

    if (method_exists($server, 'set')) {
      $server->set([
        'enable_coroutine' => true,
        'hook_flags' => SWOOLE_HOOK_ALL,
      ]);
    }

    $server->on('start', function () use ($app): void {
      if (method_exists($app, 'setLogger')) {
        // noop for now; startup hooks will be added in the next Phase 3 slice.
      }
    });

    $server->on('request', function ($request, $response) use ($handler): void {
      $serverData = $request->server ?? [];
      $headerData = $request->header ?? [];
      $normalizedServerData = [];

      foreach ($serverData as $key => $value) {
        $normalizedServerData[strtoupper((string) $key)] = $value;
      }

      foreach ($headerData as $key => $value) {
        $normalizedServerData['HTTP_' . strtoupper(str_replace('-', '_', (string) $key))] = $value;
      }

      $normalizedServerData['REQUEST_METHOD'] = strtoupper((string) ($serverData['request_method'] ?? 'GET'));
      $normalizedServerData['REQUEST_URI'] = (string) ($serverData['request_uri'] ?? '/');
      $normalizedServerData['QUERY_STRING'] = (string) ($serverData['query_string'] ?? '');
      $normalizedServerData['HTTP_HOST'] = (string) ($headerData['host'] ?? ($this->host . ':' . $this->port));
      $normalizedServerData['CONTENT_TYPE'] = (string) ($headerData['content-type'] ?? '');
      $normalizedServerData['REMOTE_ADDR'] = (string) ($serverData['remote_addr'] ?? '127.0.0.1');
      $normalizedServerData['SERVER_PROTOCOL'] = (string) ($serverData['server_protocol'] ?? 'HTTP/1.1');
      $normalizedServerData['REQUEST_SCHEME'] = (string) ($headerData['x-forwarded-proto'] ?? 'http');

      $query = $request->get ?? [];
      $query['path'] ??= $normalizedServerData['REQUEST_URI'];
      $context = new RuntimeRequestContext(
        server: $normalizedServerData,
        query: $query,
        post: $request->post ?? [],
        cookies: $request->cookie ?? [],
        files: $request->files ?? [],
        rawBody: method_exists($request, 'rawContent') ? $request->rawContent() : null,
      );

      if ($app instanceof App) {
        $app->setRuntimeRequestContext($context);
        $app->setRuntimeResponseEmitter(new OpenSwooleResponseEmitter($response));
      }

      ob_start();

      try {
        $handler();
        $content = (string) ob_get_clean();
      } catch (\Throwable $throwable) {
        if (ob_get_level() > 0) {
          ob_end_clean();
        }

        if ($response->isWritable()) {
          $response->status(500);
          $response->header('content-type', 'text/plain; charset=utf-8');
          $response->end($throwable->getMessage());
        }
        return;
      } finally {
        if ($app instanceof App) {
          $app->clearRuntimeOverrides();
        }
      }

      if (!$response->isWritable()) {
        return;
      }

      $response->end($content);
    });

    $server->start();
  }
}
