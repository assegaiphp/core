<?php

namespace Tests\Unit;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\DefaultExceptionHandler;
use Assegai\Core\Exceptions\Handlers\HttpExceptionHandler;
use Assegai\Core\Exceptions\Handlers\Support\FrameworkErrorPageRenderer;
use Assegai\Core\Exceptions\Http\ForbiddenException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Exceptions\Handlers\WhoopsErrorHandler;
use Assegai\Core\Exceptions\Handlers\WhoopsExceptionHandler;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Runtimes\RuntimeContext;
use RuntimeException;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Tests\Support\UnitTester;

class ExceptionHandlersCest
{
  private array $previousServer = [];

  public function _before(): void
  {
    $this->previousServer = $_SERVER;
    $_ENV['ENV'] = 'DEV';
    RuntimeContext::flush();
  }

  public function _after(): void
  {
    $_SERVER = $this->previousServer;
    $_ENV['ENV'] = 'DEV';
    RuntimeContext::flush();
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

  public function testWhoopsExceptionHandlerTreatsActiveRuntimeRequestsAsHttpEvenInCli(UnitTester $I): void
  {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $logger = $this->createNullLogger();
    RuntimeContext::set(RequestInterface::class, Request::createFromRuntimeContext(new RuntimeRequestContext(
      server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/runtime-error',
        'QUERY_STRING' => '',
        'REQUEST_SCHEME' => 'http',
      ],
      query: [
        'path' => '/runtime-error',
      ],
    )));

    $handler = new class($logger) extends WhoopsExceptionHandler {
      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
      }

      public function exposeResponseMode(): string
      {
        return $this->getResponseMode();
      }
    };

    $I->assertSame('html', $handler->exposeResponseMode());
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

  public function testWhoopsExceptionHandlerLogsHttpClientErrorsAsDebug(UnitTester $I): void
  {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $records = [];
    $logger = $this->createRecordingLogger($records);

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

      protected function emitErrorResponse(string $body, ContentType $contentType, int $statusCode): void
      {
        $this->emissions[] = [
          'body' => $body,
          'status' => $statusCode,
          'content_type' => $contentType->value,
        ];
      }
    };

    $handler->handle(new NotFoundException('/.well-known/appspecific/com.chrome.devtools.json'));

    $I->assertCount(1, $handler->emissions);
    $I->assertSame(404, $handler->emissions[0]['status']);
    $I->assertCount(1, $records);
    $I->assertSame('debug', $records[0]['level']);
    $I->assertStringContainsString('/.well-known/appspecific/com.chrome.devtools.json', $records[0]['message']);
  }

  public function testWhoopsExceptionHandlerLogsUnexpectedFailuresAsErrors(UnitTester $I): void
  {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $records = [];
    $logger = $this->createRecordingLogger($records);

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

      protected function emitErrorResponse(string $body, ContentType $contentType, int $statusCode): void
      {
        $this->emissions[] = [
          'body' => $body,
          'status' => $statusCode,
          'content_type' => $contentType->value,
        ];
      }
    };

    $handler->handle(new RuntimeException('Boom'));

    $I->assertCount(1, $handler->emissions);
    $I->assertSame(500, $handler->emissions[0]['status']);
    $I->assertCount(1, $records);
    $I->assertSame('error', $records[0]['level']);
    $I->assertSame('Boom', $records[0]['message']);
  }

  public function testFrameworkErrorPageRendererBuildsHtmlShell(UnitTester $I): void
  {
    $html = FrameworkErrorPageRenderer::render(404, 'Not Found', 'Page not found', 'The page you requested does not exist.');

    $I->assertStringContainsString('<!doctype html>', $html);
    $I->assertStringContainsString('Page not found', $html);
    $I->assertStringContainsString('404', $html);
    $I->assertStringContainsString('AssegaiPHP', $html);
  }

  public function testDefaultExceptionHandlerCanRenderHtmlErrors(UnitTester $I): void
  {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['CONTENT_TYPE'] = '';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';

    $logger = $this->createNullLogger();

    $handler = new class($logger) extends DefaultExceptionHandler {
      public array $emissions = [];

      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
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

    $handler->handle(new RuntimeException('Boom'));

    $I->assertCount(1, $handler->emissions);
    $I->assertSame('text/html', $handler->emissions[0]['content_type']);
    $I->assertStringContainsString('Unhandled exception', $handler->emissions[0]['body']);
  }

  public function testHttpExceptionHandlerHidesInternalDetailsInProduction(UnitTester $I): void
  {
    $_ENV['ENV'] = 'PROD';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['CONTENT_TYPE'] = '';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';

    $logger = $this->createNullLogger();

    $handler = new class($logger) extends HttpExceptionHandler {
      public array $emissions = [];

      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
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

    $handler->handle(new NotFoundException('blog/secret-page'));

    $I->assertCount(1, $handler->emissions);
    $I->assertSame('text/html', $handler->emissions[0]['content_type']);
    $I->assertStringContainsString('The requested page could not be found.', $handler->emissions[0]['body']);
    $I->assertStringNotContainsString('blog/secret-page', $handler->emissions[0]['body']);
    $I->assertStringNotContainsString('Details', $handler->emissions[0]['body']);
  }

  public function testHttpExceptionHandlerLogsHttpClientErrorsAsDebug(UnitTester $I): void
  {
    $_ENV['ENV'] = 'PROD';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['CONTENT_TYPE'] = '';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';
    $records = [];
    $logger = $this->createRecordingLogger($records);

    $handler = new class($logger) extends HttpExceptionHandler {
      public array $emissions = [];

      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
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

    $handler->handle(new NotFoundException('missing-style.css'));

    $I->assertCount(1, $handler->emissions);
    $I->assertSame(404, $handler->emissions[0]['status']);
    $I->assertCount(1, $records);
    $I->assertSame('debug', $records[0]['level']);
    $I->assertStringContainsString('/missing-style.css', $records[0]['message']);
  }

  public function testHttpExceptionHandlerShowsForbiddenFor403(UnitTester $I): void
  {
    $_ENV['ENV'] = 'PROD';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['CONTENT_TYPE'] = '';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';

    $logger = $this->createNullLogger();

    $handler = new class($logger) extends HttpExceptionHandler {
      public array $emissions = [];

      public function __construct(LoggerInterface $logger)
      {
        parent::__construct($logger);
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

    $handler->handle(new ForbiddenException('admin area'));

    $I->assertCount(1, $handler->emissions);
    $I->assertSame(403, $handler->emissions[0]['status']);
    $I->assertStringContainsString('Forbidden', $handler->emissions[0]['body']);
    $I->assertStringContainsString('You do not have permission to access this page.', $handler->emissions[0]['body']);
    $I->assertStringNotContainsString('Page not found', $handler->emissions[0]['body']);
  }

  private function createNullLogger(): LoggerInterface
  {
    return new class extends AbstractLogger {
      public function log($level, string|\Stringable $message, array $context = []): void
      {
      }
    };
  }

  /**
   * @param array<int, array{level: mixed, message: string}> $records
   */
  private function createRecordingLogger(array &$records): LoggerInterface
  {
    return new class($records) extends AbstractLogger {
      /**
       * @param array<int, array{level: mixed, message: string}> $records
       */
      public function __construct(private array &$records)
      {
      }

      public function log($level, string|\Stringable $message, array $context = []): void
      {
        $this->records[] = [
          'level' => $level,
          'message' => (string)$message,
        ];
      }
    };
  }
}
