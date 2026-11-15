<?php

declare(strict_types=1);

namespace Tests\Unit;

use Assegai\Core\App;
use Assegai\Core\ApplicationPaths;
use Assegai\Core\AssegaiFactory;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Injector;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Rendering\Engines\ViewEngine;
use Assegai\Core\Rendering\View;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Core\Util\Paths;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\Support\UnitTester;

#[Module]
final class SourceRootModule {}

final class SourceRootCest
{
  private string $fixture;
  private string $cwd;
  private array $environment;
  private array $server;
  private array $query;
  private string|false $workingDirectory;

  public function _before(): void
  {
    $this->fixture = realpath(sys_get_temp_dir()) . '/assegai-source-root-' . bin2hex(random_bytes(8));
    mkdir($this->fixture, 0700);
    mkdir("$this->fixture/unrelated");
    $this->cwd = getcwd() ?: '.';
    $this->environment = $_ENV;
    $this->server = $_SERVER;
    $this->query = $_GET;
    $this->workingDirectory = getenv('ASSEGAI_WORKING_DIR');
    RuntimeContext::flush();
    $_ENV['ENV'] = 'TEST';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_GET = ['path' => ''];
    chdir("$this->fixture/unrelated");
  }

  public function _after(): void
  {
    RuntimeContext::flush();
    $_ENV = $this->environment;
    $_SERVER = $this->server;
    $_GET = $this->query;
    $this->workingDirectory === false
      ? putenv('ASSEGAI_WORKING_DIR')
      : putenv('ASSEGAI_WORKING_DIR=' . $this->workingDirectory);
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

  public function customSourceRootBootstrapsAndRendersViewsAndComponentsWithoutSrc(UnitTester $I): void
  {
    [$root, $component] = $this->project('custom', 'Features/Pages');
    $paths = new ApplicationPaths($root, environmentFile: null);
    $this->app($root, $paths);
    $I->assertSame("$root/Features/Pages", $paths->sourceDirectory);
    $I->assertSame("$root/Features/Pages/Views", Paths::getViewDirectory());
    $I->assertDirectoryDoesNotExist("$root/src");
    $I->assertStringContainsString('view custom', ViewEngine::getInstance()->load(new View('index'))->render());
    $engine = Injector::getInstance()->get(DefaultTemplateEngine::class);
    $I->assertInstanceOf(DefaultTemplateEngine::class, $engine);
    $html = $engine->setRootComponent(new $component())->render();
    $I->assertStringContainsString('component custom', $html);
    $I->assertStringContainsString('.custom { color: green; }', $html);
  }

  public function nativeDefaultStillRendersBothKindsOfTemplate(UnitTester $I): void
  {
    [$root, $component] = $this->project('native', 'src', declareSourceRoot: false);
    $app = $this->app($root);
    $I->assertFalse($app->getPaths()->readOnly);
    $I->assertSame("$root/src", Paths::getSourceDirectory());
    $I->assertStringContainsString('view native', ViewEngine::getInstance()->load(new View('index'))->render());
    $engine = Injector::getInstance()->get(DefaultTemplateEngine::class);
    $I->assertStringContainsString('component native', $engine->setRootComponent(new $component())->render());
  }

  public function selectedSourceDirectoryRemainsPinnedAcrossRequestsAndOtherApplications(UnitTester $I): void
  {
    [$firstRoot] = $this->project('first', 'Features/First');
    [$secondRoot] = $this->project('second', 'Other/Second');
    $first = $this->app($firstRoot, new ApplicationPaths($firstRoot, environmentFile: null));
    // A running app uses its selected metadata snapshot; a later edit belongs to a new app.
    file_put_contents("$firstRoot/assegai.json", '{"sourceRoot":"missing"}');
    $second = $this->app($secondRoot, new ApplicationPaths($secondRoot, environmentFile: null));
    $I->assertSame("$secondRoot/Other/Second/Views", Paths::getViewDirectory());
    $I->assertStringContainsString('view second', ViewEngine::getInstance()->load(new View('index'))->render());
    (new ReflectionMethod($first, 'refreshRequestScope'))->invoke($first);
    $I->assertSame("$firstRoot/Features/First/Views", Paths::getViewDirectory());
    $I->assertStringContainsString('view first', ViewEngine::getInstance()->load(new View('index'))->render());
    (new ReflectionMethod($second, 'refreshRequestScope'))->invoke($second);
    $I->assertSame("$secondRoot/Other/Second", Paths::getSourceDirectory());
  }

  public function standaloneHelpersReadOnlyTheSelectedProjectWithoutDotenv(UnitTester $I): void
  {
    [$root] = $this->project('standalone', 'Application/Source');
    file_put_contents("$root/.env", "SOURCE_ROOT_SIDE_EFFECT=must-not-load\n");
    unset($_ENV['SOURCE_ROOT_SIDE_EFFECT']);
    putenv('ASSEGAI_WORKING_DIR=' . $root);
    $I->assertSame("$root/Application/Source", Paths::getSourceDirectory());
    $I->assertSame("$root/Application/Source/Views", Paths::getViewDirectory());
    $I->assertArrayNotHasKey('SOURCE_ROOT_SIDE_EFFECT', $_ENV);
    unlink("$root/assegai.json");
    $I->assertSame("$root/src/Views", Paths::getViewDirectory());
  }

  public function invalidAndEscapingSourceRootsFailWithoutFallingBackToSrc(UnitTester $I): void
  {
    [$root] = $this->project('invalid', 'src');
    file_put_contents("$root/not-a-directory", 'file');
    foreach ([null, false, 1, [], '', '.', '..', '../outside', 'src/../src', '/tmp',
      'C:/outside', 'src\\nested', '//server/share', 'src//nested', 'src/', "src\0", "src\n", 'missing', 'not-a-directory'] as $sourceRoot) {
      file_put_contents("$root/assegai.json", json_encode(['sourceRoot' => $sourceRoot], JSON_THROW_ON_ERROR));
      $I->expectThrowable(InvalidArgumentException::class, fn() => new ApplicationPaths($root, environmentFile: null));
    }
  }

  public function standaloneComponentEnginePreservesItsWorkingDirectoryOwner(UnitTester $I): void
  {
    [$selected, $component] = $this->project('component', 'Source');
    [$legacyViewRoot] = $this->project('views', 'Other');
    putenv('ASSEGAI_WORKING_DIR=' . $legacyViewRoot);
    chdir($selected);
    $html = (new DefaultTemplateEngine())->setRootComponent(new $component())->render();
    $I->assertStringContainsString('component component', $html);
    $I->assertSame("$legacyViewRoot/Other/Views", Paths::getViewDirectory());
  }

  public function activeApplicationDoesNotRequireTheProcessWorkingDirectory(UnitTester $I): void
  {
    [$root, $component] = $this->project('active', 'Features');
    $this->app($root, new ApplicationPaths($root, environmentFile: null));
    rmdir("$this->fixture/unrelated");
    $I->assertStringContainsString('component active', (new DefaultTemplateEngine())->setRootComponent(new $component())->render());
  }

  public function sourceSymlinksMustRemainInsideTheSelectedApplication(UnitTester $I): void
  {
    [$root] = $this->project('links', 'Source');
    symlink("$this->fixture/unrelated", "$root/escape");
    symlink("$root/Source", "$root/contained");
    foreach (['escape', 'escape/missing'] as $sourceRoot) {
      file_put_contents("$root/assegai.json", json_encode(['sourceRoot' => $sourceRoot], JSON_THROW_ON_ERROR));
      $I->expectThrowable(InvalidArgumentException::class, fn() => ProjectConfig::resolveSourceDirectory($root));
    }
    file_put_contents("$root/assegai.json", '{"sourceRoot":"contained"}');
    $I->assertSame("$root/Source", (new ApplicationPaths($root))->sourceDirectory);
  }

  public function invalidProjectMetadataDoesNotSelectTheLegacyDefault(UnitTester $I): void
  {
    [$root] = $this->project('metadata', 'src');
    file_put_contents("$root/assegai.json", '{invalid');
    $I->expectThrowable(\JsonException::class, fn() => ProjectConfig::resolveSourceDirectory($root));
    file_put_contents("$root/assegai.json", 'false');
    $I->expectThrowable(InvalidArgumentException::class, fn() => ProjectConfig::resolveSourceDirectory($root));
  }

  private function app(string $root, ?ApplicationPaths $paths = null): App
  {
    try {
      return AssegaiFactory::createFromProject(SourceRootModule::class, $root, paths: $paths);
    } finally {
      restore_error_handler();
      restore_exception_handler();
    }
  }

  /** @return array{string, class-string} */
  private function project(string $name, string $sourceRoot, bool $declareSourceRoot = true): array
  {
    $root = "$this->fixture/$name";
    foreach (["$sourceRoot/Views", 'config', 'public'] as $directory) {
      mkdir("$root/$directory", 0700, true);
    }
    file_put_contents("$root/assegai.json", json_encode($declareSourceRoot ? ['sourceRoot' => $sourceRoot] : new \stdClass(), JSON_THROW_ON_ERROR));
    file_put_contents("$root/composer.json", '{"name":"tests/source-root"}');
    file_put_contents("$root/.env", '');
    file_put_contents("$root/config/default.php", '<?php return [];');
    file_put_contents("$root/$sourceRoot/Views/index.php", "<p>view $name</p>");
    file_put_contents("$root/$sourceRoot/page.twig", "<p>component $name</p>");
    file_put_contents("$root/$sourceRoot/page.css", ".$name { color: green; }");
    $componentName = 'Page' . bin2hex(random_bytes(8));
    $filename = "$root/$sourceRoot/$componentName.php";
    file_put_contents($filename, <<<PHP
<?php
namespace Tests\SourceRoot;
use Assegai\Core\Attributes\Component;
use Assegai\Core\Components\AssegaiComponent;
#[Component(selector: 'app-$name', templateUrl: 'page.twig', styleUrls: ['page.css'])]
class $componentName extends AssegaiComponent {}
PHP);
    require $filename;
    return [$root, "Tests\\SourceRoot\\$componentName"];
  }
}
