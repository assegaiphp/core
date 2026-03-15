<?php

namespace Unit;

use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\UnitTester;

class WebComponentsCest
{
  private string $workspace = '';
  private string $originalWorkingDirectory = '';
  private string $componentClass = '';

  public function _before(UnitTester $I): void
  {
    $this->originalWorkingDirectory = getcwd() ?: '.';
    $this->workspace = sys_get_temp_dir() . '/assegai-web-components-' . uniqid('', true);
    $componentBasename = 'TestWebComponentPage' . str_replace('.', '', uniqid('', true));
    $componentFilename = $componentBasename . '.php';
    $templateFilename = $componentBasename . '.twig';
    $this->componentClass = "Tests\\WebComponents\\$componentBasename";

    mkdir($this->workspace . '/public/js', 0777, true);
    mkdir($this->workspace . '/public/assets', 0777, true);
    mkdir($this->workspace . '/src', 0777, true);

    file_put_contents(
      $this->workspace . '/assegai.json',
      json_encode(['webComponents' => ['enabled' => true]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    file_put_contents($this->workspace . '/public/js/assegai-components.min.js', 'console.log("wc bundle");');
    file_put_contents($this->workspace . '/public/assets/components.js', 'console.log("alt wc bundle");');
    file_put_contents(
      $this->workspace . '/src/' . $componentFilename,
      <<<PHP
<?php

namespace Tests\WebComponents;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Components\AssegaiComponent;

#[Component(
  selector: 'app-test-web-component-page',
  templateUrl: '$templateFilename'
)]
class $componentBasename extends AssegaiComponent
{
  public string \$name = 'Ada';
  public string \$quote = "Let's ship";
}
PHP
    );
    file_put_contents(
      $this->workspace . '/src/' . $templateFilename,
      <<<TWIG
<app-user-card data-props='{{ ctx.webComponentProps({ name: name, quote: quote }) }}'>
  <p>{{ name }}</p>
</app-user-card>
TWIG
    );

    require_once $this->workspace . '/src/' . $componentFilename;
    chdir($this->workspace);
  }

  public function _after(UnitTester $I): void
  {
    chdir($this->originalWorkingDirectory);
    $this->deleteDirectory($this->workspace);
  }

  public function testTheWebComponentPropsHelperEscapesJsonForHtmlAttributes(UnitTester $I): void
  {
    $encoded = web_component_props(['quote' => "Let's <ship>"]);

    $I->assertSame('{&quot;quote&quot;:&quot;Let&#039;s &lt;ship&gt;&quot;}', $encoded);
  }

  public function testTheWebComponentBundleHelpersResolveTheConfiguredBundle(UnitTester $I): void
  {
    $I->assertSame('/js/assegai-components.min.js', web_component_bundle_url($this->workspace));
    $I->assertSame(
      '<script type="module" src="/js/assegai-components.min.js"></script>',
      web_component_bundle_tag($this->workspace)
    );

    $this->writeWorkspaceConfig([
      'webComponents' => [
        'enabled' => true,
        'bundlePath' => 'public/assets/components.js',
      ],
    ]);

    $I->assertSame('/assets/components.js', web_component_bundle_url($this->workspace));
  }

  public function testTheWebComponentBundleHelpersCanBeDisabled(UnitTester $I): void
  {
    $this->writeWorkspaceConfig([
      'webComponents' => [
        'enabled' => false,
      ],
    ]);

    $I->assertNull(web_component_bundle_url($this->workspace));
    $I->assertSame('', web_component_bundle_tag($this->workspace));
  }

  public function testTheDefaultTemplateEngineInjectsTheBundleAndExposesTheTwigHelper(UnitTester $I): void
  {
    $componentClass = $this->componentClass;
    $component = new $componentClass();
    $engine = new DefaultTemplateEngine();

    $html = $engine
      ->setRootComponent($component)
      ->render();

    $I->assertStringContainsString(
      "data-props='{&quot;name&quot;:&quot;Ada&quot;,&quot;quote&quot;:&quot;Let&#039;s ship&quot;}'",
      $html
    );
    $I->assertStringContainsString(
      '<script type="module" src="/js/assegai-components.min.js"></script>',
      $html
    );
  }

  /**
   * @param array<string, mixed> $config
   */
  private function writeWorkspaceConfig(array $config): void
  {
    file_put_contents(
      $this->workspace . '/assegai.json',
      json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
  }

  private function deleteDirectory(string $directory): void
  {
    if (!is_dir($directory)) {
      return;
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
      if ($entry->isDir()) {
        rmdir($entry->getPathname());
        continue;
      }

      unlink($entry->getPathname());
    }

    rmdir($directory);
  }
}
