<?php

namespace Tests\Unit;

use Assegai\Core\AssegaiFactory;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\ControllerManager;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Responders\Responder;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\ModuleManager;
use Assegai\Core\Routing\Router;
use Mocks\RequestAwarePipelineAppModule;
use Mocks\ResponseMetadataAppModule;
use ReflectionProperty;
use Tests\Support\UnitTester;

#[Module(
  imports: [ResponseMetadataAppModule::class, RequestAwarePipelineAppModule::class],
)]
class ReusableWorkerAppModule
{
}

class ReusableWorkerCest
{
  private string $previousWorkingDirectory = '';
  private string $workspace = '';
  private string $composerConfigFilename = '';
  private string $sourceDirectory = '';
  private string $viewsDirectory = '';
  private bool $createdComposerConfig = false;
  private bool $createdSourceDirectory = false;
  private bool $createdViewsDirectory = false;

  public function _before(): void
  {
    $this->previousWorkingDirectory = getcwd() ?: '.';
    $this->workspace = dirname(__DIR__) . '/Unit/src_test';
    $this->composerConfigFilename = $this->workspace . '/composer.json';
    $this->sourceDirectory = $this->workspace . '/src';
    $this->viewsDirectory = $this->sourceDirectory . '/Views';
    $this->createdComposerConfig = false;
    $this->createdSourceDirectory = false;
    $this->createdViewsDirectory = false;

    chdir($this->workspace);
    include dirname(__DIR__, 2) . '/vendor/autoload.php';

    if (!in_array(\Mocks\MockController::class, get_declared_classes(), true)) {
      include dirname(__DIR__, 2) . '/tests/Mocks/MockController.php';
    }

    if (!is_file($this->composerConfigFilename)) {
      file_put_contents($this->composerConfigFilename, json_encode([
        'name' => 'tests/reusable-worker-app',
        'version' => '0.1.0',
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      $this->createdComposerConfig = true;
    }

    if (!is_dir($this->sourceDirectory)) {
      mkdir($this->sourceDirectory, 0777, true);
      $this->createdSourceDirectory = true;
    }

    if (!is_dir($this->viewsDirectory)) {
      mkdir($this->viewsDirectory, 0777, true);
      $this->createdViewsDirectory = true;
    }

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_SCHEME'] = 'http';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REMOTE_HOST'] = 'localhost';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['CONTENT_TYPE'] = '';
    $_SERVER['QUERY_STRING'] = '';
    $_GET['path'] = '/';
    $_POST = [];
    $_FILES = [];
    $_SESSION = [];

    $this->resetSingleton(Injector::class);
    $this->resetSingleton(ModuleManager::class);
    $this->resetSingleton(ControllerManager::class);
    $this->resetSingleton(Router::class);
    $this->resetSingleton(Request::class);
    $this->resetSingleton(Response::class);
    $this->resetSingleton(Responder::class);

    header_remove();
    http_response_code(200);
  }

  public function _after(): void
  {
    $this->resetSingleton(Injector::class);
    $this->resetSingleton(ModuleManager::class);
    $this->resetSingleton(ControllerManager::class);
    $this->resetSingleton(Router::class);
    $this->resetSingleton(Request::class);
    $this->resetSingleton(Response::class);
    $this->resetSingleton(Responder::class);

    header_remove();
    http_response_code(200);

    $logFile = $this->workspace . '/logs/assegai.log';
    if (is_file($logFile)) {
      unlink($logFile);
    }

    $logDirectory = $this->workspace . '/logs';
    if (is_dir($logDirectory)) {
      @rmdir($logDirectory);
    }

    if ($this->createdComposerConfig && is_file($this->composerConfigFilename)) {
      unlink($this->composerConfigFilename);
    }

    if ($this->createdViewsDirectory && is_dir($this->viewsDirectory)) {
      @rmdir($this->viewsDirectory);
    }

    if ($this->createdSourceDirectory && is_dir($this->sourceDirectory)) {
      @rmdir($this->sourceDirectory);
    }

    chdir($this->previousWorkingDirectory);
  }

  public function testOneAppInstanceCanHandleMultipleRequestsWithoutLeakingResponseState(UnitTester $I): void
  {
    $runtime = new class implements HttpRuntimeInterface {
      /** @var array<int, array{uri: string, method: string}> */
      public array $scenarios = [
        ['uri' => '/response-metadata/manual-status', 'method' => 'GET'],
        ['uri' => '/pipeline/request-aware', 'method' => 'GET'],
        ['uri' => '/response-metadata/headers', 'method' => 'GET'],
      ];

      public function getName(): string
      {
        return 'reusable-worker-test';
      }

      public function run(AppInterface $app, callable $handler): void
      {
        foreach ($this->scenarios as $scenario) {
          $_SERVER['REQUEST_METHOD'] = $scenario['method'];
          $_SERVER['REQUEST_URI'] = $scenario['uri'];
          $_SERVER['REQUEST_SCHEME'] = 'http';
          $_SERVER['HTTP_HOST'] = 'localhost';
          $_SERVER['REMOTE_HOST'] = 'localhost';
          $_SERVER['QUERY_STRING'] = '';
          $_SERVER['CONTENT_TYPE'] = '';
          $_GET['path'] = $scenario['uri'];
          $_POST = [];
          $_FILES = [];

          $handler();
        }
      }
    };

    $app = AssegaiFactory::create(ReusableWorkerAppModule::class, $runtime);
    $capturingEmitter = new class implements ResponseEmitterInterface {
      /** @var array<int, array{body: string, status: int|null, headers: array<string, string>}> */
      public array $emissions = [];

      public function emit(string $body, ?ResponseInterface $response = null): void
      {
        $headers = [];

        foreach ($response?->getHeaders() ?? [] as $header) {
          $headers[$header['name']] = $header['value'];
        }

        $this->emissions[] = [
          'body' => $body,
          'status' => $response?->getStatusCode(),
          'headers' => $headers,
        ];
      }
    };

    $responder = Responder::current();
    $responder->setEmitter($capturingEmitter);
    Injector::getInstance()->add(ResponseEmitterInterface::class, $capturingEmitter);

    $app->run();

    $I->assertCount(3, $capturingEmitter->emissions);

    $first = $capturingEmitter->emissions[0];
    $second = $capturingEmitter->emissions[1];
    $third = $capturingEmitter->emissions[2];

    $I->assertSame(418, $first['status']);
    $I->assertSame(200, $second['status']);
    $I->assertSame(200, $third['status']);

    $I->assertSame('pipeline/request-aware', $second['headers']['X-Request-Path'] ?? null);
    $I->assertArrayNotHasKey('X-Request-Path', $third['headers']);
    $I->assertSame('1', $third['headers']['X-Export-Version'] ?? null);
    $I->assertStringContainsString('manual-status', $first['body']);
    $I->assertStringContainsString('request-aware', $second['body']);
    $I->assertStringContainsString('headers', $third['body']);
  }

  /**
   * @param class-string $className
   * @throws \ReflectionException
   */
  private function resetSingleton(string $className): void
  {
    $instanceProperty = new ReflectionProperty($className, 'instance');
    $instanceProperty->setValue(null, null);
  }
}
