<?php

namespace Unit;

use Assegai\Core\ApiDocs\OpenApiGenerator;
use Assegai\Core\ApiDocs\PostmanCollectionGenerator;
use Assegai\Core\ApiDocs\SwaggerUiRenderer;
use Assegai\Core\ApiDocs\TypeScriptClientGenerator;
use Assegai\Core\Config\ComposerConfig;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\ControllerManager;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Http\Responses\Responders\JsonResponder;
use Assegai\Core\ModuleManager;
use Mocks\ApiDocsAppModule;
use Tests\Support\UnitTester;

class ApiDocsCest
{
  private string $workspace = '';
  private string $originalWorkingDirectory = '';

  public function _before(UnitTester $I): void
  {
    $this->originalWorkingDirectory = getcwd() ?: '.';
    $this->workspace = sys_get_temp_dir() . '/assegai-api-docs-' . uniqid('', true);
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    require_once dirname(__DIR__) . '/Mocks/MockApiDocs.php';
    mkdir($this->workspace, 0777, true);
    file_put_contents($this->workspace . '/composer.json', json_encode([
      'name' => 'tests/api-docs-app',
      'version' => '0.1.0',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($this->workspace . '/assegai.json', json_encode([
      'name' => 'Test API',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    chdir($this->workspace);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_SCHEME'] = 'http';
    $_SERVER['HTTP_HOST'] = 'localhost:5050';
    $_SERVER['REQUEST_URI'] = '/docs';
    $_GET['path'] = 'docs';

    $this->resetRequestSingleton();
  }

  public function _after(UnitTester $I): void
  {
    $this->resetRequestSingleton();
    chdir($this->originalWorkingDirectory);

    if (is_file($this->workspace . '/composer.json')) {
      unlink($this->workspace . '/composer.json');
    }

    if (is_file($this->workspace . '/assegai.json')) {
      unlink($this->workspace . '/assegai.json');
    }

    if (is_dir($this->workspace)) {
      rmdir($this->workspace);
    }
  }

  public function testOpenApiGenerationIncludesDtoSchemasAndValidation(UnitTester $I): void
  {
    $generator = new OpenApiGenerator(
      ControllerManager::getInstance(),
      ModuleManager::getInstance(),
      Request::getInstance(),
      new ComposerConfig(),
      new ProjectConfig(),
    );

    $document = $generator->generate(ApiDocsAppModule::class);

    $I->assertSame('3.1.0', $document['openapi']);
    $I->assertArrayHasKey('/posts', $document['paths']);
    $I->assertArrayHasKey('post', $document['paths']['/posts']);
    $I->assertArrayHasKey('requestBody', $document['paths']['/posts']['post']);
    $I->assertSame(
      ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'],
      array_keys($document['paths']['/posts']['post']['requestBody']['content'])
    );
    $I->assertSame(201, (int) array_key_first($document['paths']['/posts']['post']['responses']));
    $I->assertArrayHasKey('/posts/{id}', $document['paths']);
    $I->assertSame('integer', $document['paths']['/posts/{id}']['get']['parameters'][0]['schema']['type']);
    $I->assertSame('search', $document['paths']['/posts']['get']['parameters'][0]['name']);
    $I->assertSame('page', $document['paths']['/posts']['get']['parameters'][1]['name']);
    $I->assertSame('string', $document['components']['schemas']['CreatePostDTO']['properties']['title']['type']);
    $I->assertSame(1, $document['components']['schemas']['CreatePostDTO']['properties']['title']['minLength']);
    $I->assertContains('string', $document['components']['schemas']['CreatePostDTO']['properties']['title']['x-assegai-validation']);
    $I->assertSame(['title', 'body'], $document['components']['schemas']['CreatePostDTO']['required']);
    $I->assertArrayHasKey('/pages/home', $document['paths']);
    $I->assertSame(
      'text/html',
      array_key_first($document['paths']['/pages/home']['get']['responses']['200']['content'])
    );
    $I->assertSame(
      'Rendered HTML response.',
      $document['paths']['/pages/home']['get']['responses']['200']['content']['text/html']['schema']['description']
    );
    $I->assertArrayNotHasKey('View', $document['components']['schemas'] ?? []);
  }

  public function testSwaggerUiRendererTargetsTheGeneratedSpec(UnitTester $I): void
  {
    $renderer = new SwaggerUiRenderer();
    $html = $renderer->render('/openapi.json', 'Test API Docs');

    $I->assertStringContainsString('SwaggerUIBundle', $html);
    $I->assertStringContainsString('/openapi.json', $html);
    $I->assertStringContainsString('Test API Docs', $html);
  }

  public function testTheGeneratedOpenApiDocumentCanFeedPostmanAndTypeScriptOutputs(UnitTester $I): void
  {
    $generator = new OpenApiGenerator(
      ControllerManager::getInstance(),
      ModuleManager::getInstance(),
      Request::getInstance(),
      new ComposerConfig(),
      new ProjectConfig(),
    );

    $document = $generator->generate(ApiDocsAppModule::class);
    $postman = (new PostmanCollectionGenerator())->generate($document);
    $typescript = (new TypeScriptClientGenerator())->generate($document);

    $I->assertSame('Test API API Collection', $postman['info']['name']);
    $I->assertStringContainsString('createAssegaiClient', $typescript);
    $I->assertStringContainsString('createpostdto', strtolower($typescript));
    $I->assertStringContainsString('apiDocsPostsCreate', $typescript);
    $I->assertStringContainsString("baseUrl = 'http://localhost:5050'", $typescript);
  }

  public function testRawJsonResponsesCanEmitOpenApiDocumentsWithoutApiEnvelope(UnitTester $I): void
  {
    $response = Response::getInstance();
    $response->reset();
    $response->jsonRaw([
      'openapi' => '3.1.0',
      'info' => ['title' => 'Test API', 'version' => '0.1.0'],
      'paths' => [],
    ]);

    $method = new \ReflectionMethod(JsonResponder::class, 'encodePayload');
    $method->setAccessible(true);
    $payload = $method->invoke(new JsonResponder(), $response->getBody());
    $decoded = json_decode($payload, true);

    $I->assertSame('3.1.0', $decoded['openapi']);
    $I->assertArrayNotHasKey('data', $decoded);
    $I->assertFalse($response->shouldWrapJsonBody());
  }

  private function resetRequestSingleton(): void
  {
    $reflection = new \ReflectionProperty(Request::class, 'instance');
    $reflection->setAccessible(true);
    $reflection->setValue(null, null);
  }
}
