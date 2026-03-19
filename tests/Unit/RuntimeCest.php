<?php

namespace Tests\Runtime;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Interfaces\AssegaiModuleInterface;

#[Module]
class DummyAppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}

namespace Unit;

use Assegai\Core\AssegaiFactory;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\Runtimes\PhpHttpRuntime;
use Tests\Support\UnitTester;

class RuntimeCest
{
  private string $workingDirectory = '';
  private string $originalWorkingDirectory = '';
  private string $composerFile = '';
  private string $sourceDirectory = '';
  private string $viewsDirectory = '';
  private bool $createdSourceDirectory = false;
  private bool $createdViewsDirectory = false;

  public function _before(UnitTester $I): void
  {
    $this->originalWorkingDirectory = getcwd() ?: '.';
    $this->workingDirectory = dirname(__DIR__, 2) . '/tests/Unit/src_test';
    $this->composerFile = $this->workingDirectory . '/composer.json';
    $this->sourceDirectory = $this->workingDirectory . '/src';
    $this->viewsDirectory = $this->sourceDirectory . '/Views';
    chdir($this->workingDirectory);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_SCHEME'] = 'http';
    $_SERVER['HTTP_HOST'] = 'localhost:5050';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['CONTENT_TYPE'] = '';
    $_GET['path'] = '';

    if (!is_dir($this->sourceDirectory)) {
      @mkdir($this->sourceDirectory, 0777, true);
      $this->createdSourceDirectory = true;
    }

    if (!is_dir($this->viewsDirectory)) {
      @mkdir($this->viewsDirectory, 0777, true);
      $this->createdViewsDirectory = true;
    }

    if (!is_file($this->composerFile)) {
      file_put_contents($this->composerFile, json_encode([
        'name' => 'tests/runtime-app',
        'version' => '0.1.0',
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
  }

  public function _after(UnitTester $I): void
  {
    if (is_file($this->composerFile)) {
      unlink($this->composerFile);
    }

    if ($this->createdViewsDirectory && is_dir($this->viewsDirectory)) {
      rmdir($this->viewsDirectory);
    }

    if ($this->createdSourceDirectory && is_dir($this->sourceDirectory)) {
      rmdir($this->sourceDirectory);
    }

    chdir($this->originalWorkingDirectory);
  }

  public function testAssegaiFactoryUsesTheDefaultPhpRuntime(UnitTester $I): void
  {
    $app = AssegaiFactory::create('Tests\\Runtime\\DummyAppModule');

    $I->assertInstanceOf(PhpHttpRuntime::class, $app->getRuntime());
    $I->assertSame('php', $app->getRuntime()->getName());
  }

  public function testAppRunDelegatesToTheInjectedRuntime(UnitTester $I): void
  {
    $runtime = new class implements HttpRuntimeInterface {
      public int $calls = 0;
      public ?AppInterface $app = null;

      public function getName(): string
      {
        return 'fake';
      }

      public function run(AppInterface $app, callable $handler): void
      {
        $this->calls++;
        $this->app = $app;
      }
    };

    $app = AssegaiFactory::create('Tests\\Runtime\\DummyAppModule', $runtime);
    $app->run();

    $I->assertSame(1, $runtime->calls);
    $I->assertSame($app, $runtime->app);
    $I->assertSame($runtime, $app->getRuntime());
  }
}
