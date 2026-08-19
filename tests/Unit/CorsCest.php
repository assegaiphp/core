<?php

namespace Unit;

use Assegai\Core\Http\Cors\CorsOptions;
use Assegai\Core\Http\Cors\CorsProcessor;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Http\Responses\Response;
use InvalidArgumentException;
use Tests\Support\UnitTester;

class CorsCest
{
  public function testDefaultsMatchTheApplicationLevelNestStylePolicy(UnitTester $I): void
  {
    $options = new CorsOptions();

    $I->assertSame('*', $options->origin);
    $I->assertSame(['GET', 'HEAD', 'PUT', 'PATCH', 'POST', 'DELETE'], $options->methods);
    $I->assertNull($options->allowedHeaders);
    $I->assertFalse($options->credentials);
    $I->assertNull($options->maxAge);
    $I->assertFalse($options->preflightContinue);
    $I->assertSame(204, $options->optionsSuccessStatus);
  }

  public function testCredentialedPoliciesRejectWildcardOrigins(UnitTester $I): void
  {
    $I->expectThrowable(InvalidArgumentException::class, static function (): void {
      new CorsOptions(origin: '*', credentials: true);
    });

    $I->expectThrowable(InvalidArgumentException::class, static function (): void {
      CorsOptions::from(['origin' => true, 'credentials' => true]);
    });
  }

  public function testAllowedCredentialedOriginsDecorateActualResponses(UnitTester $I): void
  {
    $request = $this->createRequest(origin: 'https://console.example.com');
    $response = Response::create();
    $response->setHeader('Vary', 'Accept-Encoding');
    $processor = new CorsProcessor(new CorsOptions(
      origin: ['https://console.example.com', 'http://localhost:5173'],
      exposedHeaders: ['Location', 'X-Request-Id'],
      credentials: true,
    ));

    $processor->apply($request, $response);

    $I->assertSame('https://console.example.com', $response->getHeader('Access-Control-Allow-Origin'));
    $I->assertSame('true', $response->getHeader('Access-Control-Allow-Credentials'));
    $I->assertSame('Location, X-Request-Id', $response->getHeader('Access-Control-Expose-Headers'));
    $I->assertSame('Accept-Encoding, Origin', $response->getHeader('Vary'));
    $I->assertNull($response->getHeader('Access-Control-Allow-Methods'));
  }

  public function testDisallowedOriginsFailClosedAndStillVaryByOrigin(UnitTester $I): void
  {
    $request = $this->createRequest(origin: 'https://attacker.example');
    $response = Response::create();
    $processor = new CorsProcessor(new CorsOptions(
      origin: ['https://console.example.com'],
      credentials: true,
    ));

    $processor->apply($request, $response);

    $I->assertNull($response->getHeader('Access-Control-Allow-Origin'));
    $I->assertNull($response->getHeader('Access-Control-Allow-Credentials'));
    $I->assertSame('Origin', $response->getHeader('Vary'));
  }

  public function testPreflightResponsesIncludeMethodsHeadersCredentialsAndCaching(UnitTester $I): void
  {
    $request = $this->createRequest(
      method: 'OPTIONS',
      origin: 'http://localhost:5173',
      headers: [
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, Authorization, X-CSRF-Token',
      ],
    );
    $response = Response::create();
    $processor = new CorsProcessor(new CorsOptions(
      origin: ['http://localhost:5173'],
      methods: ['GET', 'POST', 'PATCH', 'DELETE'],
      credentials: true,
      maxAge: 600,
    ));

    $I->assertTrue($processor->isPreflightRequest($request));
    $I->assertTrue($processor->shouldShortCircuitPreflight($request));

    $processor->apply($request, $response);

    $I->assertSame('http://localhost:5173', $response->getHeader('Access-Control-Allow-Origin'));
    $I->assertSame('true', $response->getHeader('Access-Control-Allow-Credentials'));
    $I->assertSame('GET, POST, PATCH, DELETE', $response->getHeader('Access-Control-Allow-Methods'));
    $I->assertSame(
      'Content-Type, Authorization, X-CSRF-Token',
      $response->getHeader('Access-Control-Allow-Headers')
    );
    $I->assertSame('600', $response->getHeader('Access-Control-Max-Age'));
    $I->assertSame(
      'Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
      $response->getHeader('Vary')
    );
  }

  public function testOrdinaryOptionsRequestsAreNotTreatedAsPreflights(UnitTester $I): void
  {
    $request = $this->createRequest(method: 'OPTIONS', origin: 'http://localhost:5173');
    $processor = new CorsProcessor(new CorsOptions());

    $I->assertFalse($processor->isPreflightRequest($request));
    $I->assertFalse($processor->shouldShortCircuitPreflight($request));
  }

  public function testOriginCallbacksCanResolvePoliciesPerRequest(UnitTester $I): void
  {
    $request = $this->createRequest(origin: 'https://tenant.example.com');
    $response = Response::create();
    $processor = new CorsProcessor(new CorsOptions(
      origin: static fn(string $origin): bool => str_ends_with($origin, '.example.com'),
      credentials: true,
    ));

    $processor->apply($request, $response);

    $I->assertSame('https://tenant.example.com', $response->getHeader('Access-Control-Allow-Origin'));
    $I->assertSame('Origin', $response->getHeader('Vary'));
  }

  /**
   * @param array<string, string> $headers
   */
  private function createRequest(
    string $method = 'GET',
    string $origin = '',
    array $headers = [],
  ): Request
  {
    return Request::createFromRuntimeContext(new RuntimeRequestContext(
      server: [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => '/api/widgets',
        'QUERY_STRING' => '',
        'HTTP_HOST' => 'api.example.com',
        'REQUEST_SCHEME' => 'https',
        'HTTP_ORIGIN' => $origin,
        ...$headers,
      ],
      query: ['path' => '/api/widgets'],
    ));
  }
}
