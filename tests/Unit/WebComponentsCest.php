<?php

namespace Unit;

use Assegai\Core\App;
use Assegai\Core\AssegaiFactory;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Config as FrameworkConfig;
use Assegai\Core\Config\AppConfig;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Rendering\Engines\ViewEngine;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Rendering\DocumentProperties;
use Assegai\Core\Rendering\View;
use Assegai\Core\Rendering\ViewProperties;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Core\WebComponents\WebComponentConfiguration;
use Assegai\Core\WebComponents\WebComponentSupport;
use InvalidArgumentException;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use Tests\Support\UnitTester;

#[Module]
class WebComponentsAppModule {}

class WebComponentsCest
{
  private string $workspace = '';
  private string $originalWorkingDirectory = '';
  private string $componentClass = '';
  private string|false $originalProjectRoot = false;
  private array $originalEnvironment = [];
  private array $originalServer = [];

  public function _before(UnitTester $I): void
  {
    $this->originalWorkingDirectory = getcwd() ?: '.';
    $this->originalProjectRoot = getenv('ASSEGAI_WORKING_DIR');
    $this->originalEnvironment = $_ENV;
    $this->originalServer = $_SERVER;
    putenv('ASSEGAI_WORKING_DIR');
    RuntimeContext::flush();
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
    mkdir($this->workspace . '/src/Views');
    file_put_contents($this->workspace . '/src/Views/page.php', '<p>Page</p>');
    file_put_contents($this->workspace . '/.env', '');
    file_put_contents($this->workspace . '/composer.json', '{"name":"tests/web-components"}');

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
    RuntimeContext::flush();
    putenv($this->originalProjectRoot === false ? 'ASSEGAI_WORKING_DIR' : 'ASSEGAI_WORKING_DIR=' . $this->originalProjectRoot);
    $_ENV = $this->originalEnvironment;
    $_SERVER = $this->originalServer;
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

  public function testDocumentPropertiesAllowLinkAttributeMaps(UnitTester $I): void
  {
    unset($_ENV['app']);
    $GLOBALS['config']['app'] = [
      'links' => [
        '/css/site.css',
        ['preconnect', 'https://cdn.example.test'],
        [
          'rel' => 'search',
          'type' => 'application/opensearchdescription+xml',
          'title' => 'AssegaiPHP',
          'href' => '/opensearch.xml',
        ],
      ],
      'headScriptUrls' => [],
      'bodyScriptUrls' => [],
      'htmxLink' => '',
    ];

    $props = DocumentProperties::fromArray([]);
    $headTags = $props->generateHeadAssetTags();

    $I->assertStringContainsString("<link rel='stylesheet' href='/css/site.css' />", $headTags);
    $I->assertStringContainsString("<link rel='preconnect' href='https://cdn.example.test' />", $headTags);
    $I->assertStringContainsString("<link rel='search' type='application/opensearchdescription+xml' title='AssegaiPHP' href='/opensearch.xml' />", $headTags);
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

  public function testApplicationOverridesSuppressBothRenderersAndHelpersWithoutChangingWorkspace(UnitTester $I): void
  {
    $this->writeMarker(['bundleUrl' => '/untrusted-marker.js']);
    $originalWorkspaceConfig = file_get_contents($this->workspace . '/assegai.json');
    $this->writeAppConfig([
      'webComponents' => ['enabled' => false, 'hotReload' => ['enabled' => false]],
      'app' => ['bodyScriptUrls' => [[
        'src' => '/release/components.js', 'type' => 'module',
        'integrity' => 'sha256-pinned', 'crossorigin' => 'anonymous',
      ]]],
    ], 'secure');
    $this->createApp();
    // Neither stale legacy configuration nor a different cwd can override the App snapshot.
    $_ENV['app'] = $GLOBALS['config']['app'] = ['bodyScriptUrls' => ['/wrong.js']];
    $componentClass = $this->componentClass;
    $engine = (new DefaultTemplateEngine())->setRootComponent(new $componentClass());
    $view = new View('page');
    chdir($this->originalWorkingDirectory);

    foreach ([$engine->render(), ViewEngine::getInstance()->load($view)->render()] as $html) {
      $I->assertSame(1, substr_count($html, "src='/release/components.js'"));
      $I->assertStringContainsString("integrity='sha256-pinned'", $html);
      $I->assertStringContainsString("crossorigin='anonymous'", $html);
      $I->assertStringNotContainsString('assegai-components.min.js', $html);
      $I->assertStringNotContainsString('wc-hot-reload', $html);
      $I->assertStringNotContainsString('untrusted-marker', $html);
      $I->assertStringNotContainsString('/wrong.js', $html);
    }
    $I->assertFalse(WebComponentSupport::getConfiguration()->isBundleEnabled());
    $I->assertFalse(WebComponentSupport::getConfiguration()->isHotReloadEnabled());
    $I->assertNull(web_component_bundle_url());
    $I->assertSame('', web_component_bundle_tag());
    $I->assertSame('', web_component_runtime_tags());
    $I->assertSame('', WebComponentSupport::renderHotReloadTag());
    $I->assertSame('', web_component_runtime_tags($this->workspace . '/.'));
    $I->assertSame($originalWorkspaceConfig, file_get_contents($this->workspace . '/assegai.json'));
  }

  public function testApplicationWithoutOverridesPreservesDevelopmentInBothRenderers(UnitTester $I): void
  {
    $this->writeMarker();
    $this->createApp();
    $componentClass = $this->componentClass;
    foreach ([
      (new DefaultTemplateEngine())->setRootComponent(new $componentClass())->render(),
      ViewEngine::getInstance()->load(new View('page'))->render(),
    ] as $html) {
      $I->assertStringContainsString('<script type="module" src="/js/assegai-components.min.js"></script>', $html);
      $I->assertStringContainsString('window.location.reload()', $html);
    }
  }

  public function testBundleEmissionAndPollingAreIndependent(UnitTester $I): void
  {
    $this->writeMarker();
    foreach ([[false, true], [true, false], [false, false], [true, true]] as [$bundle, $polling]) {
      $this->writeAppConfig(['webComponents' => ['enabled' => $bundle, 'hotReload' => ['enabled' => $polling]]]);
      $this->createApp();
      $configuration = WebComponentSupport::getConfiguration();
      $I->assertSame($bundle, $configuration->isBundleEnabled());
      $I->assertSame($polling, $configuration->isHotReloadEnabled());
      $I->assertSame($bundle, str_contains(web_component_runtime_tags(), '<script type="module"'));
      $I->assertSame($polling, str_contains(web_component_runtime_tags(), 'window.location.reload()'));
    }
  }

  public function testApplicationSnapshotReadsFragmentsOnlyOnceAndKeepsSelectionIdentity(UnitTester $I): void
  {
    file_put_contents($this->workspace . '/config/default.php', <<<'PHP'
<?php
$selection = new stdClass();
$selection->identity = bin2hex(random_bytes(8));
return [
  'selection' => $selection,
  'app' => ['bodyScriptUrls' => [['src' => '/release/' . $selection->identity . '.js', 'integrity' => 'sha256-' . $selection->identity]]],
  'webComponents' => ['enabled' => false, 'hotReload' => ['enabled' => false]],
];
PHP);
    $app = $this->createApp();
    $selection = AppConfig::current()->get('selection');
    $I->assertSame($selection, AppConfig::current()->get('selection'));
    $I->assertStringContainsString($selection->identity, DocumentProperties::fromArray([])->generateBodyScriptImportTags());
    // Accessors must not re-execute configuration PHP, even when it changes on disk.
    file_put_contents($this->workspace . '/config/default.php', '<?php throw new RuntimeException("Unexpected second load");');
    $detached = AppConfig::current();
    $detached->set('webComponents.enabled', true);
    $I->assertFalse(AppConfig::current()->get('webComponents.enabled'));
    $I->assertSame('', web_component_runtime_tags());
    (new ReflectionMethod($app, 'refreshRequestScope'))->invoke($app);
    $I->assertSame($selection, AppConfig::current()->get('selection'));
    $I->assertSame('', web_component_runtime_tags());
  }

  public function testWorkspaceAndMarkerAreReadOncePerRequestAndRefreshForNextRequest(UnitTester $I): void
  {
    $this->writeMarker();
    $app = $this->createApp();
    $first = web_component_runtime_tags();
    $snapshot = WebComponentSupport::getConfiguration();
    $this->writeWorkspaceConfig(['webComponents' => ['bundleUrl' => '/assets/components.js']]);
    $this->writeMarker(['bundleUrl' => '/changed.js']);
    $I->assertSame($first, web_component_runtime_tags());
    $I->assertSame('/js/assegai-components.min.js', web_component_bundle_url());
    $I->assertSame($snapshot, WebComponentSupport::getConfiguration($this->workspace));

    (new ReflectionMethod($app, 'refreshRequestScope'))->invoke($app);
    $I->assertNotSame($snapshot, WebComponentSupport::getConfiguration());
    $I->assertSame('/assets/components.js', web_component_bundle_url());
    $I->assertStringContainsString('/changed.js', web_component_runtime_tags());
  }

  public function testApplicationPolicyAndRootDoNotLeakBetweenProjects(UnitTester $I): void
  {
    $this->writeAppConfig(['webComponents' => ['enabled' => false, 'hotReload' => ['enabled' => false]]]);
    $first = $this->createApp();
    $secondRoot = $this->workspace . '/second';
    mkdir($secondRoot . '/config', 0777, true);
    mkdir($secondRoot . '/src');
    file_put_contents($secondRoot . '/.env', '');
    file_put_contents($secondRoot . '/composer.json', '{}');
    file_put_contents($secondRoot . '/assegai.json', '{"webComponents":{"bundleUrl":"https://cdn.example.test/second.js"}}');
    file_put_contents($secondRoot . '/config/default.php', '<?php return ["app" => ["title" => "Second app"]];');
    $I->assertSame('', web_component_bundle_tag());
    $I->assertSame('https://cdn.example.test/second.js', web_component_bundle_url($secondRoot));

    $second = $this->createApp($secondRoot);
    $I->assertSame('https://cdn.example.test/second.js', web_component_bundle_url());
    $I->assertSame('Second app', DocumentProperties::fromArray([])->title);
    (new ReflectionMethod($first, 'refreshRequestScope'))->invoke($first);
    $I->assertSame(realpath($this->workspace), WebComponentSupport::getConfiguration()->workingDirectory);
    $I->assertSame(realpath($this->workspace), AppConfig::current()->getWorkingDirectory());
    $I->assertSame('', web_component_runtime_tags());
    (new ReflectionMethod($second, 'refreshRequestScope'))->invoke($second);
    $I->assertSame('https://cdn.example.test/second.js', web_component_bundle_url());
  }

  public function testRuntimeOverridesRejectInvalidTypesAndUnknownFields(UnitTester $I): void
  {
    foreach ([null, false, 'disabled', ['enabled' => null], ['enabled' => 'false'], ['enable' => false], ['bundleUrl' => ''],
      ['bundleUrl' => []], ['hotReload' => false], ['hotReload' => ['enabled' => 0]],
      ['hotReload' => ['path' => '']], ['hotReload' => ['pollInterval' => 0]],
      ['hotReload' => ['pollInterval' => '500']], ['hotReload' => ['enable' => false]],
    ] as $invalid) {
      $I->expectThrowable(InvalidArgumentException::class, fn() => new WebComponentConfiguration($this->workspace, $invalid));
    }
  }

  public function testPartialOverridesRespectFragmentOrderAndKeepWorkspaceWatcherSettings(UnitTester $I): void
  {
    $this->writeWorkspaceConfig(['webComponents' => [
      'enabled' => false, 'bundlePath' => 'public/assets/components.js',
      'hotReload' => ['enabled' => false, 'path' => 'public/assets/watch.json', 'pollInterval' => 900],
    ]]);
    file_put_contents($this->workspace . '/public/assets/watch.json', '{"active":true,"version":"one"}');
    $this->writeAppConfig(['webComponents' => ['enabled' => false, 'hotReload' => ['enabled' => true]]]);
    $this->writeAppConfig(['webComponents' => ['enabled' => false, 'hotReload' => ['pollInterval' => 800]]], 'local');
    $this->writeAppConfig(['webComponents' => ['enabled' => true, 'hotReload' => ['pollInterval' => 600]]], 'secure');
    $this->createApp();
    $configuration = WebComponentSupport::getConfiguration();
    $I->assertTrue($configuration->isBundleEnabled());
    $I->assertSame('/assets/components.js', $configuration->getBundleUrl());
    $I->assertSame([
      'bundleUrl' => '/assets/components.js', 'interval' => 600, 'markerUrl' => '/assets/watch.json',
    ], $configuration->getHotReloadState());
    $I->assertStringContainsString('const interval = 600;', web_component_runtime_tags());
  }

  public function testExplicitBundleOverrideWinsOverWorkspaceAliasesAndDoesNotFallBackWhenMissing(UnitTester $I): void
  {
    $this->writeWorkspaceConfig(['webComponents' => ['bundlePath' => 'public/assets/components.js']]);
    $this->writeAppConfig(['webComponents' => ['bundleUrl' => '/missing.js']]);
    $this->createApp();
    $I->assertNull(web_component_bundle_url());
    $I->assertSame('', web_component_runtime_tags());
    $this->writeAppConfig(['webComponents' => ['bundleUrl' => 'https://cdn.example.test/bundle.js?a=1&b=2']]);
    $this->createApp();
    $I->assertStringContainsString('bundle.js?a=1&amp;b=2', web_component_bundle_tag());
  }

  public function testInactiveExpiredAndMalformedMarkersCannotStartPolling(UnitTester $I): void
  {
    foreach ([['active' => false], ['expiresAt' => gmdate(DATE_ATOM, time() - 300)]] as $state) {
      $this->writeMarker($state);
      $I->assertSame('', WebComponentSupport::renderHotReloadTag($this->workspace));
    }
    file_put_contents($this->workspace . '/public/.assegai/wc-hot-reload.json', '{invalid');
    $I->assertSame('', WebComponentSupport::renderHotReloadTag($this->workspace));
  }

  public function testMarkerUrlsCannotBreakOutOfThePollingScript(UnitTester $I): void
  {
    $this->writeMarker(['bundleUrl' => '/</script><script>alert(1)</script>.js']);
    $tag = WebComponentSupport::renderHotReloadTag($this->workspace);
    $I->assertSame(1, substr_count($tag, '</script>'));
    $I->assertStringContainsString('\\u003C/script\\u003E', $tag);
  }

  public function testLifecycleClearsConfigurationAfterAConfigurationFailure(UnitTester $I): void
  {
    $app = $this->createApp();
    $this->writeAppConfig(['webComponents' => ['enabled' => 'false']]);
    $app->configure(new AppConfig());
    $emitter = new class implements ResponseEmitterInterface {
      public string $body = '';
      public function emit(string $body, ?ResponseInterface $response = null): void { $this->body = $body; }
    };
    $app->setRuntimeResponseEmitter($emitter);
    $_ENV['ENV'] = 'production';
    (new ReflectionMethod($app, 'runDefaultHttpLifecycle'))->invoke($app);
    $I->assertNotEmpty($emitter->body);
    $I->assertNull(RuntimeContext::get(WebComponentConfiguration::class));
    $I->expectThrowable(LogicException::class, fn() => AppConfig::current());
  }

  public function testBootstrapRejectsInvalidOverridesAndClearsItsPartialScope(UnitTester $I): void
  {
    foreach ([null, ['hotReload' => ['enabled' => 'false']]] as $invalid) {
      $this->writeAppConfig(['webComponents' => $invalid]);
      $I->expectThrowable(InvalidArgumentException::class, fn() => $this->createApp());
      $I->assertNull(RuntimeContext::get(WebComponentConfiguration::class));
      $I->expectThrowable(LogicException::class, fn() => AppConfig::current());
    }
  }

  public function testSuccessfulLifecycleClearsSnapshotsAndNextRequestRecoversDefaults(UnitTester $I): void
  {
    $this->writeAppConfig(['webComponents' => ['enabled' => false, 'hotReload' => ['enabled' => false]]]);
    $app = $this->createApp()->enableCors();
    $app->setRuntimeResponseEmitter(new class implements ResponseEmitterInterface {
      public function emit(string $body, ?ResponseInterface $response = null): void {}
    });
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $_SERVER['HTTP_ORIGIN'] = 'https://example.test';
    $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'GET';
    (new ReflectionMethod($app, 'runDefaultHttpLifecycle'))->invoke($app);
    $I->assertNull(RuntimeContext::get(WebComponentConfiguration::class));
    $I->expectThrowable(LogicException::class, fn() => AppConfig::current());
    $this->writeAppConfig([]);
    $app->configure(new AppConfig($this->workspace));
    (new ReflectionMethod($app, 'refreshRequestScope'))->invoke($app);
    $I->assertSame('/js/assegai-components.min.js', web_component_bundle_url());
  }

  private function createApp(?string $root = null): App
  {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['HTTP_HOST'] = 'localhost';
    try {
      return AssegaiFactory::createFromProject(WebComponentsAppModule::class, $root ?? $this->workspace);
    } finally {
      restore_error_handler();
      restore_exception_handler();
    }
  }

  private function writeAppConfig(array $config, string $fragment = 'default'): void
  {
    file_put_contents($this->workspace . '/config/' . $fragment . '.php', '<?php return ' . var_export($config, true) . ';');
  }

  private function writeMarker(array $overrides = []): void
  {
    file_put_contents($this->workspace . '/public/.assegai/wc-hot-reload.json', json_encode(array_replace([
      'active' => true, 'bundleUrl' => '/js/assegai-components.min.js', 'version' => 'build-1',
      'expiresAt' => gmdate(DATE_ATOM, time() + 300),
    ], $overrides), JSON_UNESCAPED_SLASHES));
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
