<?php

namespace Unit;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Interfaces\AppInterface;
use ReflectionClass;
use Tests\Mocks\MockApp;
use Tests\Support\UnitTester;

class RequestCest
{
  protected ?Request $request = null;

  public function _before(): void
  {
    $this->request = $this->createRequest();
  }

  public function _after(): void
  {
    $this->resetRequestSingleton();
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_SERVER = [];
    unset($_ENV['TRUSTED_PROXIES']);
    putenv('TRUSTED_PROXIES');
  }

  public function testTheGetInstanceMethod(UnitTester $I): void
  {
    $I->assertInstanceOf(Request::class, $this->request);
  }

  public function testTheAppGetterAndSetter(UnitTester $I): void
  {
    $app = new MockApp();

    $I->assertNull($this->request->getApp());
    $this->request->setApp($app);
    $I->assertInstanceOf(AppInterface::class, $this->request->getApp());
  }

  public function testTheRequestCanHandleUrlEncodedFormPosts(UnitTester $I): void
  {
    $request = $this->createRequest(
      server: [
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
      ],
      post: [
        'name' => 'Shaka',
        'age' => '23',
      ],
    );

    $body = $request->getBody();

    $I->assertSame('Shaka', $body?->name);
    $I->assertSame('23', $body?->age);
    $I->assertSame([], $request->getFile());
  }

  public function testTheRequestCanHandleMultipartFormPostsWithFiles(UnitTester $I): void
  {
    $request = $this->createRequest(
      server: [
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'multipart/form-data; boundary=----AssegaiBoundary',
      ],
      post: [
        'name' => 'Shaka',
      ],
      files: [
        'avatar' => [
          'name' => 'avatar.png',
          'type' => 'image/png',
          'tmp_name' => '/tmp/avatar.png',
          'error' => 0,
          'size' => 1234,
        ],
      ],
    );

    $body = $request->getBody();
    $files = $request->getFile();

    $I->assertSame('Shaka', $body?->name);
    $I->assertIsObject($files);
    $I->assertSame('avatar.png', $files->avatar['name']);
  }

  public function testTheRequestCanHandleFileOnlyMultipartPosts(UnitTester $I): void
  {
    $request = $this->createRequest(
      server: [
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'multipart/form-data; boundary=----AssegaiBoundary',
      ],
      files: [
        'avatar' => [
          'name' => 'avatar.png',
          'type' => 'image/png',
          'tmp_name' => '/tmp/avatar.png',
          'error' => 0,
          'size' => 1234,
        ],
      ],
    );

    $body = $request->getBody();
    $files = $request->getFile();

    $I->assertInstanceOf(\stdClass::class, $body);
    $I->assertSame([], get_object_vars($body));
    $I->assertIsObject($files);
    $I->assertSame('avatar.png', $files->avatar['name']);
  }

  public function testTheRequestNormalizesForwardedHosts(UnitTester $I): void
  {
    $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';
    $request = $this->createRequest(
      server: [
        'HTTP_HOST' => 'tenant.example.com:8080',
        'HTTP_X_FORWARDED_HOST' => 'Admin.Example.com:8443, proxy.internal',
        'REMOTE_ADDR' => '127.0.0.1',
      ],
    );

    $I->assertSame('admin.example.com', $request->getHostName());
    unset($_ENV['TRUSTED_PROXIES']);
  }

  public function testTheRequestIgnoresForwardedHostsFromUntrustedClients(UnitTester $I): void
  {
    unset($_ENV['TRUSTED_PROXIES']);
    $request = $this->createRequest(
      server: [
        'HTTP_HOST' => 'tenant.example.com:8080',
        'HTTP_X_FORWARDED_HOST' => 'admin.example.com',
        'REMOTE_ADDR' => '203.0.113.10',
      ],
    );

    $I->assertSame('tenant.example.com', $request->getHostName());
  }

  public function testTheRequestReadsTrustedProxiesFromTheProcessEnvironment(UnitTester $I): void
  {
    putenv('TRUSTED_PROXIES=127.0.0.1');
    $request = $this->createRequest(
      server: [
        'HTTP_HOST' => 'tenant.example.com',
        'HTTP_X_FORWARDED_HOST' => 'admin.example.com',
        'REMOTE_ADDR' => '127.0.0.1',
      ],
    );

    $I->assertSame('admin.example.com', $request->getHostName());
  }

  public function testTheRequestUriCannotBeOverriddenByAPathQueryParameter(UnitTester $I): void
  {
    $request = $this->createRequest(
      server: [
        'REQUEST_URI' => '/public?path=/admin',
      ],
      get: [
        'path' => '/admin',
      ],
    );

    $I->assertSame('/public', $request->getPath());
  }

  private function createRequest(
    array $server = [],
    array $get = ['path' => '/test'],
    array $post = [],
    array $files = [],
  ): Request {
    $this->resetRequestSingleton();

    $_GET = $get;
    $_POST = $post;
    $_FILES = $files;
    $_SERVER = array_merge([
      'REQUEST_URI' => '/test',
      'REQUEST_METHOD' => 'GET',
      'HTTP_HOST' => 'localhost',
      'REMOTE_HOST' => 'localhost',
    ], $server);

    return Request::getInstance();
  }

  private function resetRequestSingleton(): void
  {
    $requestReflection = new ReflectionClass(Request::class);
    $instanceProperty = $requestReflection->getProperty('instance');
    $instanceProperty->setValue(null, null);
  }
}
