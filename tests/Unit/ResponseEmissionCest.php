<?php

namespace Tests\Unit;

use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Responders\JsonResponder;
use Assegai\Core\Http\Responses\Responders\Responder;
use Assegai\Core\Http\Responses\Response;
use ReflectionException;
use ReflectionProperty;
use Tests\Support\UnitTester;

class ResponseEmissionCest
{
  public function _before(): void
  {
    header_remove();
    http_response_code(200);
    Response::setInstance(Response::create());
    $this->resetSingleton(Responder::class);
  }

  public function _after(): void
  {
    header_remove();
    http_response_code(200);
    Response::setInstance(null);
    $this->resetSingleton(Responder::class);
  }

  public function testPhpResponseEmitterDoesNotTerminateExecution(UnitTester $I): void
  {
    $response = Response::create();
    $response->plainText('Hello world');
    $response->setStatus(202);
    $emitter = new PhpResponseEmitter();
    $continued = false;

    ob_start();
    $emitter->emit('Hello world', $response);
    $continued = true;
    $output = ob_get_clean();

    $I->assertSame('Hello world', $output);
    $I->assertTrue($continued);
    $I->assertSame(202, http_response_code());
  }

  public function testJsonResponderReturnsAfterRawEmission(UnitTester $I): void
  {
    $response = Response::create();
    $response->jsonRaw([
      'openapi' => '3.1.0',
      'info' => ['title' => 'Test API'],
    ]);

    $emitter = $this->createCapturingEmitter();
    $responder = new JsonResponder($emitter);

    $responder->respond($response);

    $I->assertCount(1, $emitter->emissions);
    $I->assertSame('3.1.0', json_decode($emitter->emissions[0]['body'], true)['openapi']);
  }

  public function testRedirectResponsesEmitOnceWithoutFallingThrough(UnitTester $I): void
  {
    $response = Response::create();
    $response->redirect('/next', 302);
    $emitter = $this->createCapturingEmitter();
    $responder = Responder::getInstance();
    $responder->setEmitter($emitter);

    $responder->respond($response);

    $I->assertCount(1, $emitter->emissions);
    $I->assertStringContainsString('Redirecting to', $emitter->emissions[0]['body']);
    $I->assertSame('/next', $emitter->emissions[0]['response']?->getRedirectUrl());
  }

  /**
   * @return object{emissions: array<int, array{body: string, response: ResponseInterface|null}>}&ResponseEmitterInterface
   */
  private function createCapturingEmitter(): object
  {
    return new class implements ResponseEmitterInterface {
      /** @var array<int, array{body: string, response: ResponseInterface|null}> */
      public array $emissions = [];

      public function emit(string $body, ?ResponseInterface $response = null): void
      {
        $this->emissions[] = [
          'body' => $body,
          'response' => $response,
        ];
      }
    };
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
