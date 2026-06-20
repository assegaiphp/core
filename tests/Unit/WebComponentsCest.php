<?php

namespace Unit;

use Assegai\Core\Config as FrameworkConfig;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Rendering\DocumentProperties;
use Assegai\Core\Rendering\ViewProperties;
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
    mkdir($this->workspace . '/public/.assegai', 0777, true);
    mkdir($this->workspace . '/public/assets', 0777, true);
    mkdir($this->workspace . '/config', 0777, true);
    mkdir($this->workspace . '/src', 0777, true);

    file_put_contents(
      $this->workspace . '/assegai.json',
      json_encode(['webComponents' => ['enabled' => true]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    file_put_contents(
      $this->workspace . '/config/default.php',
      <<<'PHP'
<?php

return [
  'app' => [
    'title' => 'Configured App Title',
    'description' => 'Configured app description',
    'author' => 'Assegai Tests',
    'lang' => 'fr',
    'links' => ['/css/site.css'],
    'headScriptUrls' => ['/js/runtime.js'],
    'bodyScriptUrls' => ['/js/body.js'],
    'favicon' => ['/assets/favicon.svg', 'image/svg+xml'],
  ],
];
PHP
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
    unset($GLOBALS['config']);
    FrameworkConfig::hydrate($this->workspace);
  }

  public function _after(UnitTester $I): void
  {
    chdir($this->originalWorkingDirectory);
    unset($GLOBALS['config']);
    $this->deleteDirectory($this->workspace);
  }

  public function testTheWcPropsHelperEscapesJsonForHtmlAttributes(UnitTester $I): void
  {
    $encoded = wc_props(['quote' => "Let's <ship>"]);

    $I->assertSame('{&quot;quote&quot;:&quot;Let&#039;s &lt;ship&gt;&quot;}', $encoded);
    $I->assertSame($encoded, web_component_props(['quote' => "Let's <ship>"]));
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

  public function testTheWebComponentBundleHelpersSkipMissingLocalBundles(UnitTester $I): void
  {
    unlink($this->workspace . '/public/js/assegai-components.min.js');

    $this->writeWorkspaceConfig([
      'webComponents' => [
        'enabled' => true,
        'output' => 'public/js/assegai-components.min.js',
      ],
    ]);

    $I->assertNull(web_component_bundle_url($this->workspace));
    $I->assertSame('', web_component_bundle_tag($this->workspace));
  }

  public function testTheWebComponentRuntimeTagsInjectHotReloadWhenWatchIsActive(UnitTester $I): void
  {
    $this->writeWorkspaceConfig([
      'webComponents' => [
        'enabled' => true,
        'hotReload' => [
          'enabled' => true,
          'path' => 'public/.assegai/wc-hot-reload.json',
          'pollInterval' => 500,
        ],
      ],
    ]);

    file_put_contents(
      $this->workspace . '/public/.assegai/wc-hot-reload.json',
      json_encode([
        'active' => true,
        'bundleUrl' => '/js/assegai-components.min.js',
        'version' => 'build-1',
        'interval' => 500,
        'expiresAt' => gmdate(DATE_ATOM, time() + 300),
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $tags = web_component_runtime_tags($this->workspace);

    $I->assertStringContainsString('<script type="module" src="/js/assegai-components.min.js"></script>', $tags);
    $I->assertStringContainsString('/.assegai/wc-hot-reload.json', $tags);
    $I->assertStringContainsString('marker.version', $tags);
    $I->assertStringContainsString('window.location.reload()', $tags);
  }


  public function testTheWebComponentRuntimeTagsIgnoreInactiveHotReloadState(UnitTester $I): void
  {
    $this->writeWorkspaceConfig([
      'webComponents' => [
        'enabled' => true,
        'hotReload' => [
          'enabled' => true,
          'path' => 'public/.assegai/wc-hot-reload.json',
          'pollInterval' => 500,
        ],
      ],
    ]);

    file_put_contents(
      $this->workspace . '/public/.assegai/wc-hot-reload.json',
      json_encode([
        'active' => false,
        'bundleUrl' => '/js/assegai-components.min.js',
        'version' => 'build-1',
        'interval' => 500,
        'expiresAt' => gmdate(DATE_ATOM, time() + 300),
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $tags = web_component_runtime_tags($this->workspace);

    $I->assertStringContainsString('<script type="module" src="/js/assegai-components.min.js"></script>', $tags);
    $I->assertStringNotContainsString('/.assegai/wc-hot-reload.json', $tags);
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

  public function testViewPropertiesMergeGlobalDocumentConfigWithPerViewOverrides(UnitTester $I): void
  {
    $props = ViewProperties::fromArray([
      'title' => 'Posts',
      'headScriptUrls' => ['/js/page.js'],
      'bodyScriptUrls' => ['/js/page-body.js'],
      'favicon' => ['/custom.ico', 'image/x-icon'],
    ]);

    $I->assertSame('Posts', $props->title);
    $I->assertSame('fr', $props->lang);
    $I->assertSame(['/css/site.css'], $props->links);
    $I->assertSame(['/js/runtime.js', '/js/page.js'], $props->headScriptUrls);
    $I->assertSame(['/js/body.js', '/js/page-body.js'], $props->bodyScriptUrls);
    $I->assertSame(['/custom.ico', 'image/x-icon'], $props->favicon);
  }

  public function testDocumentPropertiesMergeGlobalDocumentConfigForComponentRendering(UnitTester $I): void
  {
    $props = DocumentProperties::fromArray([
      'title' => 'Homepage',
      'headScriptUrls' => ['/js/home.js'],
    ]);

    $I->assertSame('Homepage', $props->title);
    $I->assertSame('fr', $props->lang);
    $I->assertSame(['/css/site.css'], $props->links);
    $I->assertSame(['/assets/favicon.svg', 'image/svg+xml'], $props->favicon);
    $I->assertSame(['/js/runtime.js', '/js/home.js'], $props->headScriptUrls);
  }

  public function testDocumentPropertiesAllowScriptAttributeMaps(UnitTester $I): void
  {
    unset($_ENV['app']);
    $GLOBALS['config']['app'] = [
      'title' => 'Configured App Title',
      'description' => 'Configured app description',
      'author' => 'Assegai Tests',
      'lang' => 'fr',
      'links' => ['/css/site.css'],
      'favicon' => ['/assets/favicon.svg', 'image/svg+xml'],
      'headScriptUrls' => [
        '/js/runtime.js',
        ['src' => '/js/head-module.js', 'type' => 'module', 'data-app' => 'demo'],
      ],
      'bodyScriptUrls' => [
        '/js/body.js',
        ['src' => '/js/body-module.js', 'type' => 'module', 'crossorigin' => 'anonymous'],
      ],
      'htmxLink' => '',
    ];

    $props = DocumentProperties::fromArray([]);

    $headScriptTags = $props->generateHeadAssetTags();
    $bodyScriptTags = $props->generateBodyScriptImportTags();

    $I->assertStringContainsString("<script defer src='/js/runtime.js'></script>", $headScriptTags);
    $I->assertStringContainsString("<script defer src='/js/head-module.js' type='module' data-app='demo'></script>", $headScriptTags);
    $I->assertStringContainsString("<script src='/js/body.js' defer></script>", $bodyScriptTags);
    $I->assertStringContainsString("<script src='/js/body-module.js' type='module' crossorigin='anonymous' defer></script>", $bodyScriptTags);
  }

  public function testTheDefaultTemplateEngineUsesGlobalDocumentConfig(UnitTester $I): void
  {
    $componentClass = $this->componentClass;
    $component = new $componentClass();
    $engine = new DefaultTemplateEngine();

    $html = $engine
      ->setRootComponent($component)
      ->render();

    $I->assertStringContainsString('<meta name="description" content="Configured app description">', $html);
    $I->assertStringContainsString('<meta name="author" content="Assegai Tests">', $html);
    $I->assertStringContainsString("<link rel='shortcut icon' href='/assets/favicon.svg' type='image/svg+xml' />", $html);
    $I->assertStringContainsString("<link rel='stylesheet' href='/css/site.css' />", $html);
    $I->assertStringContainsString("<script defer src='/js/runtime.js'></script>", $html);
    $I->assertStringContainsString("<script src='/js/body.js' defer></script>", $html);
    $I->assertStringContainsString('<html lang="fr">', $html);
  }

  public function testDefaultTemplateEngineUsesStructuredComponentDocumentProps(UnitTester $I): void
  {
    $componentClass = $this->writeComponentWithMeta('StructuredProps', <<<'PHP'
[
  'props' => [
    'headScriptUrls' => ['/js/page.js'],
    'bodyScriptUrls' => ['/js/page-body.js'],
    'lang' => 'es',
    'htmxLink' => '',
  ],
]
PHP);

    $component = new $componentClass();
    $engine = new DefaultTemplateEngine();

    $html = $engine
      ->setRootComponent($component)
      ->render();

    $I->assertStringContainsString("<script defer src='/js/page.js'></script>", $html);
    $I->assertStringContainsString("<script src='/js/page-body.js' defer></script>", $html);
    $I->assertStringContainsString('<html lang="es">', $html);
  }

  public function testDefaultTemplateEnginePreservesLegacyRawHeadProps(UnitTester $I): void
  {
    $componentClass = $this->writeComponentWithMeta('RawHeadProps', <<<'PHP'
[
  'props' => '<meta name="legacy-head" content="ok">',
  'htmxLink' => '',
]
PHP);

    $component = new $componentClass();
    $engine = new DefaultTemplateEngine();

    $html = $engine
      ->setRootComponent($component)
      ->render();

    $I->assertStringContainsString('<meta name="legacy-head" content="ok">', $html);
  }

  public function testTheDefaultTemplateEngineSkipsEmptyConfiguredBodyScriptUrls(UnitTester $I): void
  {
    unset($_ENV['app']);
    $GLOBALS['config']['app'] = [
      'title' => 'Configured App Title',
      'description' => 'Configured app description',
      'author' => 'Assegai Tests',
      'lang' => 'fr',
      'links' => ['/css/site.css'],
      'headScriptUrls' => ['/js/runtime.js'],
      'bodyScriptUrls' => [''],
      'htmxLink' => '',
      'favicon' => ['/assets/favicon.svg', 'image/svg+xml'],
    ];

    $componentClass = $this->componentClass;
    $component = new $componentClass();
    $engine = new DefaultTemplateEngine();

    $html = $engine
      ->setRootComponent($component)
      ->render();

    $I->assertStringNotContainsString("<script src='' defer></script>", $html);
    $I->assertStringNotContainsString('<script src=""></script>', $html);
  }

  private function writeComponentWithMeta(string $suffix, string $meta): string
  {
    $componentBasename = 'TestWebComponent' . preg_replace('/[^A-Za-z0-9]/', '', $suffix) . str_replace('.', '', uniqid('', true));
    $componentFilename = $componentBasename . '.php';
    $templateFilename = $componentBasename . '.twig';
    $selector = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $suffix));
    $componentClass = "Tests\\WebComponents\\$componentBasename";

    file_put_contents($this->workspace . '/src/' . $templateFilename, '<p>{{ name }}</p>');
    file_put_contents(
      $this->workspace . '/src/' . $componentFilename,
      <<<PHP
<?php

namespace Tests\WebComponents;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Components\AssegaiComponent;

#[Component(
  selector: 'app-$selector',
  templateUrl: '$templateFilename',
  meta: $meta
)]
class $componentBasename extends AssegaiComponent
{
  public string \$name = 'Ada';
}
PHP
    );

    require_once $this->workspace . '/src/' . $componentFilename;

    return $componentClass;
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
