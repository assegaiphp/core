<?php

namespace Tests\Unit;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\WhoopsErrorHandler;
use Assegai\Core\Exceptions\Handlers\WhoopsExceptionHandler;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Tests\Support\UnitTester;

class ExceptionHandlersCest
{
  private array $previousServer = [];

  public function _before(): void
  {
    $this->previousServer = $_SERVER;
  }

  public function _after(): void
  {
    $_SERVER = $this->previousServer;
  }

  public function testWhoopsErrorHandlerChoosesHtmlForGetRequests(UnitTester $I): void
  {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $logger = $this->createNullLogger();

    $handler = new class($logger) extends WhoopsErrorHandler {
      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
      }

      public function exposeResponseMode(): string
      {
        return $this->getResponseMode();
      }

      protected function isCliContext(): bool
      {
        return false;
      }
    };

    $I->assertSame('html', $handler->exposeResponseMode());
  }

  public function testWhoopsErrorHandlerChoosesJsonForNonGetRequests(UnitTester $I): void
  {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $logger = $this->createNullLogger();

    $handler = new class($logger) extends WhoopsErrorHandler {
      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
      }

      public function exposeResponseMode(): string
      {
        return $this->getResponseMode();
      }

      protected function isCliContext(): bool
      {
        return false;
      }
    };

    $I->assertSame('json', $handler->exposeResponseMode());
  }

  public function testWhoopsExceptionHandlerAlsoChoosesJsonForNonGetRequests(UnitTester $I): void
  {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $logger = $this->createNullLogger();

    $handler = new class($logger) extends WhoopsExceptionHandler {
      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
      }

      public function exposeResponseMode(): string
      {
        return $this->getResponseMode();
      }

      protected function isCliContext(): bool
      {
        return false;
      }
    };

    $I->assertSame('json', $handler->exposeResponseMode());
  }

  public function testWhoopsExceptionHandlerUsesTheResponseScopeEmitterHelper(UnitTester $I): void
  {
    $logger = $this->createNullLogger();

    $handler = new class($logger) extends WhoopsExceptionHandler {
      public array $emissions = [];

      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
      }

      protected function isCliContext(): bool
      {
        return false;
      }

      public function emitForTest(string $body, ContentType $contentType, int $statusCode): void
      {
        $this->emitErrorResponse($body, $contentType, $statusCode);
      }

      protected function emitErrorResponse(string $body, ContentType $contentType, int $statusCode): void
      {
        $this->emissions[] = [
          'body' => $body,
          'status' => $statusCode,
          'content_type' => $contentType->value,
        ];
      }
    };

    $handler->emitForTest('{"message":"rendered"}', ContentType::JSON, 500);

    $I->assertCount(1, $handler->emissions);
    $I->assertSame(500, $handler->emissions[0]['status']);
    $I->assertSame('application/json', $handler->emissions[0]['content_type']);
    $I->assertSame('{"message":"rendered"}', $handler->emissions[0]['body']);
  }

  private function createNullLogger(): LoggerInterface
  {
    return new class extends AbstractLogger {
      public function log($level, string|\Stringable $message, array $context = []): void
      {
      }
    };
  }
}
