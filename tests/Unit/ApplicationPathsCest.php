<?php

declare(strict_types=1);

namespace Tests\Unit;

use Assegai\Core\ApplicationPaths;
use Assegai\Core\AssegaiFactory;
use Assegai\Core\Config;
use Assegai\Core\Config\AppConfig;
use Assegai\Core\Config\ComposerConfig;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Files\TempFile;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Http\Responses\Interfaces\FileResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Interceptors\FileInterceptorOptions;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Core\Util\Debug\Log;
use Assegai\Core\Util\Paths;
use InvalidArgumentException;
use LogicException;
use ReflectionProperty;
use Tests\Support\UnitTester;

final class ApplicationPathsCest
{
  private string $fixture;
  private string $cwd;
  private array $environment;
  private array $server;
  private array $get;
  private string|false $workingDirectoryOverride;
  private bool $createdApp = false;

  public function _before(): void
  {
    $this->fixture = realpath(sys_get_temp_dir()) . '/assegai-path-contract-' . bin2hex(random_bytes(10));
    $this->cwd = getcwd() ?: '.';
    $this->environment = $_ENV;
    $this->server = $_SERVER;
    $this->get = $_GET;
    $this->workingDirectoryOverride = getenv('ASSEGAI_WORKING_DIR');
    $this->createdApp = false;
    RuntimeContext::flush();
    foreach (['app/src/Views', 'app/public', 'configuration', 'secrets', 'state', 'dependencies', 'unrelated'] as $directory) {
      mkdir("$this->fixture/$directory", 0700, true);
    }
    file_put_contents("$this->fixture/app/assegai.json", '{}');
    file_put_contents("$this->fixture/dependencies/composer.json", '{"name":"tests/source-free-artifact"}');
    file_put_contents("$this->fixture/configuration/default.php", "<?php return ['marker' => 'base', 'app' => ['title' => 'Generated application']];");
    file_put_contents("$this->fixture/configuration/auth.php", "<?php return ['authMarker' => 'selected'];");
    file_put_contents("$this->fixture/configuration/production.php", "<?php return ['productionMarker' => true];");
    file_put_contents("$this->fixture/configuration/local.php", "<?php throw new \\LogicException('Wrong secret configuration');");
    file_put_contents("$this->fixture/configuration/secure.php", "<?php throw new \\LogicException('Wrong secret configuration');");
    file_put_contents("$this->fixture/secrets/local.php", "<?php return ['marker' => 'local'];");
    file_put_contents("$this->fixture/secrets/secure.php", "<?php return ['marker' => 'secret'];");
    file_put_contents("$this->fixture/secrets/.env", "PATH_CONTRACT_SECRET=external-only\n");
    file_put_contents("$this->fixture/app/.env", "PATH_CONTRACT_SECRET=wrong-file\n");
    $_ENV['ENV'] = 'PROD';
    unset($_ENV['PATH_CONTRACT_SECRET']);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['CONTENT_TYPE'] = '';
    $_GET = ['path' => '/'];
    chdir("$this->fixture/unrelated");
  }

  public function _after(): void
  {
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }
    RuntimeContext::flush();
    $_ENV = $this->environment;
    $_SERVER = $this->server;
    $_GET = $this->get;
    $this->workingDirectoryOverride === false
      ? putenv('ASSEGAI_WORKING_DIR')
      : putenv('ASSEGAI_WORKING_DIR=' . $this->workingDirectoryOverride);
    if ($this->createdApp) {
      restore_error_handler();
      restore_exception_handler();
    }
    chdir($this->cwd);
    $files = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($this->fixture, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
      $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->fixture);
  }

  public function pathsSelectIndependentRootsAndValidateInputs(UnitTester $I): void
  {
    $paths = $this->paths();
    $I->assertSame("$this->fixture/app", $paths->applicationRoot);
    $I->assertSame("$this->fixture/secrets/secure.php", $paths->configurationFile('secure.php'));
    $I->assertSame("$this->fixture/configuration/auth.php", $paths->configurationFile('auth.php'));
    RuntimeContext::set(ApplicationPaths::class, $paths);
    $I->assertSame("$this->fixture/app/src/Views", Paths::getViewDirectory());
    $I->assertSame("$this->fixture/state/cache", Paths::getCacheDirectory());
    $I->assertSame("$this->fixture/state/uploads", (new FileInterceptorOptions())->dest);
    $I->assertSame("$this->fixture/state/uploads/image.png", (new TempFile(name: 'image.png'))->getTargetFilename());
    $I->assertSame('/explicit/uploads', (new FileInterceptorOptions(dest: '/explicit/uploads'))->dest);
    $I->expectThrowable(InvalidArgumentException::class, fn() => new ApplicationPaths('/does-not-exist'));
    $I->expectThrowable(InvalidArgumentException::class, fn() => new ApplicationPaths("invalid\0path"));
    $I->expectThrowable(InvalidArgumentException::class, fn() => new ApplicationPaths("$this->fixture/app", stateDirectory: 'relative'));
    $I->expectThrowable(InvalidArgumentException::class, fn() => new ApplicationPaths("$this->fixture/app", environmentFile: 'https://example.com/env'));
    $I->expectThrowable(InvalidArgumentException::class, fn() => AssegaiFactory::createFromProject('unused', $this->fixture, paths: $paths));
  }

  public function configurationsRetainTheirOwnPathsAndPreventRuntimeWrites(UnitTester $I): void
  {
    $paths = $this->paths();
    RuntimeContext::set(ApplicationPaths::class, $paths);
    $paths->loadEnvironment();
    $config = new AppConfig(paths: $paths);
    $composer = new ComposerConfig($paths);
    $project = new ProjectConfig(paths: $paths);
    $I->assertSame('external-only', $_ENV['PATH_CONTRACT_SECRET']);
    $I->assertSame('secret', $config->get('marker'));
    $I->assertSame('selected', $config->get('authMarker'));
    $I->assertSame('secret', config('secure.marker'));
    $I->assertTrue($config->get('productionMarker'));
    $I->assertSame('tests/source-free-artifact', $composer->get('name'));

    $other = new ApplicationPaths("$this->fixture/unrelated", environmentFile: null);
    RuntimeContext::set(ApplicationPaths::class, $other);
    $config->load();
    $I->assertSame('secret', $config->get('marker'));
    $I->assertSame("$this->fixture/dependencies/composer.json", $composer->getConfigFilename());
    foreach ([$config, $composer, $project] as $snapshot) {
      $I->expectThrowable(LogicException::class, fn() => $snapshot->commit());
    }
    $I->expectThrowable(LogicException::class, fn() => Config::updateWorkspaceConfig('language', 'php'));
    $I->expectThrowable(LogicException::class, fn() => Config::updateWorkspaceConfig('language', 'php', ''));
    $I->expectThrowable(LogicException::class, fn() => Config::updateWorkspaceConfig('language', 'php', '0'));
    $I->assertSame('{}', file_get_contents("$this->fixture/app/assegai.json"));
    $I->assertSame('secret', (new AppConfig("$this->fixture/app", $paths))->get('marker'));
  }

  public function absoluteEnvironmentFilesAreNotPrefixedWithTheApplicationRoot(UnitTester $I): void
  {
    foreach (['//server/share/.env', 'C:/configuration/.env', 'C:\\configuration\\.env'] as $file) {
      $paths = new ApplicationPaths("$this->fixture/app", environmentFile: $file);
      $I->assertSame($file, $paths->environmentFile);
    }
    $paths = new ApplicationPaths("$this->fixture/app", environmentFile: 'configuration/.env');
    $I->assertSame($paths->applicationRoot . DIRECTORY_SEPARATOR . 'configuration/.env', $paths->environmentFile);
  }

  public function windowsSeparatorClassificationAcceptsNativeAndNormalizedUncPaths(UnitTester $I): void
  {
    // Isolate a Windows separator constant without claiming Windows filesystem coverage.
    $script = <<<'PHP'
namespace Assegai\Core;
const DIRECTORY_SEPARATOR = '\\';
require $argv[1];
$absolute = new \ReflectionMethod(ApplicationPaths::class, 'absolute');
$result = [];
foreach (['//server/share/application', '\\\\server\\share\\application', 'C:/application', 'C:\\application', '\\application'] as $path) {
  $result['directories'][] = $absolute->invoke(null, $path);
  $result['files'][] = $absolute->invoke(null, $path, 'C:\\base');
}
$result['relativeFile'] = $absolute->invoke(null, 'settings/.env', 'C:\\base');
try {
  $absolute->invoke(null, 'relative');
  $result['relativeDirectoryRejected'] = false;
} catch (\InvalidArgumentException) {
  $result['relativeDirectoryRejected'] = true;
}
echo json_encode($result, JSON_THROW_ON_ERROR);
PHP;
    $process = proc_open(
      [PHP_BINARY, '-r', $script, dirname(__DIR__, 2) . '/src/ApplicationPaths.php'],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes,
    );
    $I->assertTrue(is_resource($process));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $I->assertSame(0, proc_close($process), $errors);
    $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    $expected = ['//server/share/application', '\\\\server\\share\\application', 'C:/application', 'C:\\application', '\\application'];
    $I->assertSame($expected, $result['directories']);
    $I->assertSame($expected, $result['files']);
    $I->assertSame('C:\\base\\settings/.env', $result['relativeFile']);
    $I->assertTrue($result['relativeDirectoryRejected']);
  }

  public function explicitEnvironmentDisableDoesNotReadAnAdjacentEnvFile(UnitTester $I): void
  {
    $paths = new ApplicationPaths("$this->fixture/app", environmentFile: null);
    $paths->loadEnvironment();
    $I->assertArrayNotHasKey('PATH_CONTRACT_SECRET', $_ENV);
    $this->paths()->loadEnvironment();
    $I->assertSame('external-only', $_ENV['PATH_CONTRACT_SECRET']);
  }

  public function missingSelectedEnvironmentFailsWithoutSourceFallback(UnitTester $I): void
  {
    $paths = new ApplicationPaths("$this->fixture/app", environmentFile: "$this->fixture/secrets/missing.env");
    $I->expectThrowable(\Dotenv\Exception\InvalidPathException::class, fn() => $paths->loadEnvironment());
    $I->assertArrayNotHasKey('PATH_CONTRACT_SECRET', $_ENV);
  }

  public function failedBootstrapRestoresThePreviousPathContext(UnitTester $I): void
  {
    $previous = $this->paths();
    RuntimeContext::set(ApplicationPaths::class, $previous);
    $paths = new ApplicationPaths("$this->fixture/app", environmentFile: 'missing.env');
    $this->createdApp = true;
    $I->expectThrowable(\Dotenv\Exception\InvalidPathException::class, fn() => AssegaiFactory::create('unused', paths: $paths));
    $I->assertSame($previous, Paths::application());
  }

  public function legacyDefaultsRemainAvailableWithoutAnExplicitContract(UnitTester $I): void
  {
    $paths = new ApplicationPaths("$this->fixture/app");
    $I->assertSame("$this->fixture/app/config", $paths->configurationDirectory);
    $I->assertSame("$this->fixture/app/config", $paths->secretsDirectory);
    $I->assertSame("$this->fixture/app", $paths->composerDirectory);
    $I->assertSame(DEFAULT_STORAGE_PATH, (new FileInterceptorOptions())->dest);
    $I->assertSame(DEFAULT_STORAGE_PATH . '/image.png', (new TempFile(name: 'image.png'))->getTargetFilename());
  }

  public function selectedLogStateDoesNotLeakAcrossApplications(UnitTester $I): void
  {
    $one = $this->paths();
    mkdir("$this->fixture/state-two");
    $two = new ApplicationPaths("$this->fixture/app", stateDirectory: "$this->fixture/state-two");
    RuntimeContext::set(ApplicationPaths::class, $one);
    Log::init();
    Log::info('contract', 'first-app');
    Log::error('contract', 'first-error');
    RuntimeContext::set(ApplicationPaths::class, $two);
    Log::init();
    Log::warn('contract', 'second-app');
    $I->assertStringContainsString('first-app', file_get_contents("$this->fixture/state/logs/assegai.log"));
    $I->assertStringContainsString('first-error', file_get_contents("$this->fixture/state/logs/assegai.log"));
    $I->assertStringNotContainsString('second-app', file_get_contents("$this->fixture/state/logs/assegai.log"));
    $I->assertStringContainsString('second-app', file_get_contents("$this->fixture/state-two/logs/assegai.log"));
    $I->assertDirectoryDoesNotExist("$this->fixture/app/logs");
    $I->assertDirectoryDoesNotExist("$this->fixture/unrelated/logs");
  }

  public function relocatedApplicationRendersAndWritesOnlyExternalState(UnitTester $I): void
  {
    $namespace = 'PathContract' . bin2hex(random_bytes(5));
    $source = str_replace('NAMESPACE_TOKEN', $namespace, <<<'PHP'
<?php
namespace NAMESPACE_TOKEN;
use Assegai\Core\ApplicationPaths;
use Assegai\Core\Attributes\Component;
use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Files\TempFile;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Rendering\View;
use Assegai\Core\Rendering\Engines\ViewEngine;
use Assegai\Core\Util\Debug\Log;
use Assegai\Core\Util\Paths;
#[Controller('probe')]
final class ControllerToken {
  public function __construct(private ApplicationPaths $paths, private \Assegai\Core\Rendering\Engines\DefaultTemplateEngine $templates) {}
  #[Get]
  public function index(): Response {
    Log::info('contract', 'rendered-request');
    $_SESSION['external'] = 'persisted';
    return Response::current()->jsonRaw([
      'root' => Paths::getWorkingDirectory(),
      'injectedRoot' => $this->paths->applicationRoot,
      'html' => ViewEngine::getInstance()->load(new View('fragment', ['message' => 'source-free']))->render(),
      'upload' => (new TempFile(name: 'image.png'))->getTargetFilename(),
      'sessionPath' => session_save_path(),
      'configuration' => \Assegai\Core\Config::get('marker'),
    ]);
  }
  #[Get('component')]
  public function component(): string {
    return $this->templates->setRootComponent(new ComponentToken())->render();
  }
}
#[Component(
  selector: 'path-contract',
  templateUrl: './screen.twig',
  styleUrls: ['./screen.css'],
  scriptUrls: ['./screen.js'],
)]
final class ComponentToken extends \Assegai\Core\Components\AssegaiComponent {
  public string $message = 'generated component';
}
#[Module(controllers: [ControllerToken::class], declarations: [ComponentToken::class])]
final class ModuleToken {}
PHP);
    file_put_contents("$this->fixture/app/src/Module.php", $source);
    file_put_contents("$this->fixture/app/src/Views/fragment.php", '<main><?= htmlspecialchars($message) ?></main>');
    file_put_contents("$this->fixture/app/src/screen.twig", '<article>{{ message }}{% include "part.html" %}</article>');
    file_put_contents("$this->fixture/app/src/part.html", '<span>adjacent html</span>');
    file_put_contents("$this->fixture/app/src/screen.css", 'article { color: blue; }');
    file_put_contents("$this->fixture/app/src/screen.js", 'window.pathContract = true;');
    file_put_contents("$this->fixture/app/public/screen.css", 'body { color: green; }');
    $hashes = $this->applicationHashes();
    require "$this->fixture/app/src/Module.php";
    $paths = $this->paths();
    $this->createdApp = true;
    $app = AssegaiFactory::createFromProject("$namespace\\ModuleToken", paths: $paths);
    $I->assertSame($paths, $app->getPaths());
    $engine = new ReflectionProperty(DefaultTemplateEngine::class, 'templatesDirectory');
    $templateEngine = new ReflectionProperty($app, 'templateEngine');
    $I->assertSame("$this->fixture/app/src", $engine->getValue($templateEngine->getValue($app)));
    $emitter = new class implements FileResponseEmitterInterface {
      public ?ResponseInterface $response = null;
      public ?string $filename = null;
      public function emit(string $body, ?ResponseInterface $response = null): void { $this->response = $response; }
      public function emitFile(string $filename, ?ResponseInterface $response = null): void {
        $this->filename = $filename;
        $this->response = $response;
      }
    };
    $previousSessionPath = session_save_path();
    $app->setRuntimeRequestContext(new RuntimeRequestContext(
      server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/probe', 'HTTP_HOST' => 'localhost'],
      query: ['path' => '/probe'],
    ));
    $app->setRuntimeResponseEmitter($emitter);
    $app->run();
    $body = (array) $emitter->response?->getBody();
    $I->assertSame("$this->fixture/app", $body['root'] ?? null);
    $I->assertSame("$this->fixture/app", $body['injectedRoot'] ?? null);
    $I->assertStringContainsString('<main>source-free</main>', $body['html'] ?? '');
    $I->assertSame("$this->fixture/state/uploads/image.png", $body['upload'] ?? null);
    $I->assertSame("$this->fixture/state/sessions", $body['sessionPath'] ?? null);
    $I->assertSame('secret', $body['configuration'] ?? null);
    $I->assertSame($previousSessionPath, session_save_path());
    $I->assertNotEmpty(glob("$this->fixture/state/sessions/sess_*"));
    $I->assertDirectoryDoesNotExist("$this->fixture/app/logs");
    $I->assertDirectoryDoesNotExist("$this->fixture/unrelated/logs");
    $I->assertSame('external-only', $_ENV['PATH_CONTRACT_SECRET']);
    $app->setRuntimeRequestContext(new RuntimeRequestContext(
      server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/probe/component', 'HTTP_HOST' => 'localhost'],
      query: ['path' => '/probe/component'],
    ));
    $app->run();
    $html = $emitter->response?->getBody();
    $I->assertIsString($html);
    $I->assertStringContainsString('generated component<span>adjacent html</span>', $html);
    $I->assertStringContainsString('article { color: blue; }', $html);
    $I->assertStringContainsString('window.pathContract = true;', $html);
    $app->setRuntimeRequestContext(new RuntimeRequestContext(
      server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/screen.css', 'HTTP_HOST' => 'localhost'],
      query: ['path' => '/screen.css'],
    ));
    $app->run();
    $I->assertSame("$this->fixture/app/public/screen.css", $emitter->filename);
    $I->assertSame($hashes, $this->applicationHashes());
    $I->assertNull(Paths::application());
  }

  private function applicationHashes(): array
  {
    $hashes = [];
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$this->fixture/app", \FilesystemIterator::SKIP_DOTS)) as $file) {
      if ($file->isFile()) {
        $hashes[$file->getPathname()] = hash_file('sha256', $file->getPathname());
      }
    }
    ksort($hashes);
    return $hashes;
  }

  private function paths(): ApplicationPaths
  {
    return new ApplicationPaths(
      applicationRoot: "$this->fixture/app",
      configurationDirectory: "$this->fixture/configuration",
      secretsDirectory: "$this->fixture/secrets",
      stateDirectory: "$this->fixture/state",
      composerDirectory: "$this->fixture/dependencies",
      environmentFile: "$this->fixture/secrets/.env",
    );
  }
}
