<?php

namespace Assegai\Core\Runtimes;

use Assegai\Core\App;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Http\Responses\Emitters\OpenSwooleResponseEmitter;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\Runtimes\OpenSwoole\Interfaces\OpenSwooleHttpServerInterface;
use Assegai\Core\Runtimes\OpenSwoole\Interfaces\OpenSwooleServerFactoryInterface;
use Assegai\Core\Runtimes\OpenSwoole\NativeOpenSwooleServerFactory;

class OpenSwooleHttpRuntime implements HttpRuntimeInterface
{
  /**
   * @param array<string, mixed> $settings
   */
  public function __construct(
    private readonly string $host = '127.0.0.1',
    private readonly int $port = 9501,
    private readonly array $settings = [],
    private ?OpenSwooleServerFactoryInterface $serverFactory = null,
  )
  {
    $this->serverFactory ??= new NativeOpenSwooleServerFactory();
  }

  public function getName(): string
  {
    return 'openswoole';
  }

  public function getHost(): string
  {
    return $this->host;
  }

  public function getPort(): int
  {
    return $this->port;
  }

  /**
   * @return array<string, mixed>
   */
  public function getSettings(): array
  {
    return $this->settings;
  }

  public function run(AppInterface $app, callable $handler): void
  {
    $server = $this->serverFactory->create($this->host, $this->port);
    $server->set($this->resolveServerSettings());

    $server->on('workerStart', function () use ($app): void {
      if ($app instanceof App) {
        $app->boot();
      }
    });

    $server->on('workerExit', function () use ($app): void {
      if ($app instanceof App) {
        $app->shutdown();
      }
    });

    $server->on('request', function ($request, $response) use ($app, $handler): void {
      $context = $this->createRuntimeRequestContext($request);

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

        if ($app instanceof App) {
          $app->handleRuntimeThrowable($throwable);
          return;
        }

        if ($response->isWritable()) {
          $response->status(500);
          $response->header('content-type', 'text/plain; charset=utf-8');
          $response->end('Internal Server Error');
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

  /**
   * @param object $request
   * @return RuntimeRequestContext
   */
  private function createRuntimeRequestContext(object $request): RuntimeRequestContext
  {
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

    return new RuntimeRequestContext(
      server: $normalizedServerData,
      query: $query,
      post: $request->post ?? [],
      cookies: $request->cookie ?? [],
      files: $request->files ?? [],
      rawBody: method_exists($request, 'rawContent') ? $request->rawContent() : null,
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function resolveServerSettings(): array
  {
    $settings = [
      'enable_coroutine' => $this->settings['enableCoroutine'] ?? true,
    ];

    $hookFlags = $this->settings['hookFlags'] ?? 'all';
    $resolvedHookFlags = $this->resolveHookFlags($hookFlags);

    if ($resolvedHookFlags !== null) {
      $settings['hook_flags'] = $resolvedHookFlags;
    }

    $optionalMappings = [
      'workerNum' => 'worker_num',
      'taskWorkerNum' => 'task_worker_num',
      'maxRequest' => 'max_request',
      'daemonize' => 'daemonize',
      'logFile' => 'log_file',
      'pidFile' => 'pid_file',
    ];

    foreach ($optionalMappings as $sourceKey => $targetKey) {
      if (!array_key_exists($sourceKey, $this->settings)) {
        continue;
      }

      $settings[$targetKey] = $this->settings[$sourceKey];
    }

    return $settings;
  }

  private function resolveHookFlags(mixed $hookFlags): mixed
  {
    if ($hookFlags === null || $hookFlags === false) {
      return null;
    }

    if (is_int($hookFlags)) {
      return $hookFlags;
    }

    if (is_string($hookFlags)) {
      $normalized = strtolower(trim($hookFlags));

      if ($normalized === '' || $normalized === 'none') {
        return null;
      }

      if ($normalized === 'all' && defined('SWOOLE_HOOK_ALL')) {
        return constant('SWOOLE_HOOK_ALL');
      }
    }

    return $hookFlags;
  }
}
