<?php

namespace Tests\Unit;

use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Rendering\Engines\ViewEngine;
use Assegai\Core\Rendering\View;
use Assegai\Core\Rendering\ViewProperties;
use ReflectionException;
use ReflectionProperty;
use Tests\Support\UnitTester;

class ViewEngineCest
{
  private string $previousWorkingDirectory = '';
  private string $viewsDirectory = '';
  private string $viewFilename = '';
  private string $siblingTemplateFilename = '';

  public function _before(): void
  {
    $this->previousWorkingDirectory = getcwd() ?: '.';
    chdir(dirname(__DIR__) . '/Unit/src_test');

    $this->viewsDirectory = getcwd() . '/src/Views';
    $this->viewFilename = $this->viewsDirectory . '/render-test.php';
    $this->siblingTemplateFilename = getcwd() . '/src/secret.php';

    if (!is_dir($this->viewsDirectory)) {
      mkdir($this->viewsDirectory, 0777, true);
    }

    file_put_contents($this->viewFilename, '<main><?= $message ?></main>');
    file_put_contents($this->siblingTemplateFilename, '<?php echo "secret";');
    $this->resetSingleton(ViewEngine::class);
  }

  public function _after(): void
  {
    if (is_file($this->viewFilename)) {
      unlink($this->viewFilename);
    }

    if (is_file($this->siblingTemplateFilename)) {
      unlink($this->siblingTemplateFilename);
    }

    if (is_dir($this->viewsDirectory)) {
      @rmdir($this->viewsDirectory);
      @rmdir(dirname($this->viewsDirectory));
    }

    $this->resetSingleton(ViewEngine::class);
    chdir($this->previousWorkingDirectory);
  }

  public function testViewEngineRenderReturnsHtmlString(UnitTester $I): void
  {
    $html = ViewEngine::getInstance()
      ->load(new View(
        'render-test',
        data: ['message' => 'Rendered output'],
        props: new ViewProperties(title: 'Render Test'),
      ))
      ->render();

    $I->assertIsString($html);
    $I->assertStringContainsString('<title>Render Test</title>', $html);
    $I->assertStringContainsString('<main>Rendered output</main>', $html);
    $I->assertStringContainsString('<!DOCTYPE html>', $html);
  }

  public function testViewRejectsTraversalOutsideViewsDirectory(UnitTester $I): void
  {
    $I->expectThrowable(
      RenderingException::class,
      static fn(): View => new View('../secret'),
    );
  }

  /**
   * @param class-string $className
   * @throws ReflectionException
   */
  private function resetSingleton(string $className): void
  {
    $instanceProperty = new ReflectionProperty($className, 'instance');
    $instanceProperty->setValue(null, null);
  }
}
