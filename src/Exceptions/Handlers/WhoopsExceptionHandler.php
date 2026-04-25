<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\Concerns\EmitsErrorResponses;
use Assegai\Core\Exceptions\Handlers\Support\FrameworkErrorPageRenderer;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Interfaces\ExceptionHandlerInterface;
use Assegai\Core\Http\HttpStatus;
use Psr\Log\LoggerInterface;
use Throwable;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * The Whoops error handler for the Assegai framework.
 *
 * @package Assegai\Core\Exceptions\Handlers
 */
class WhoopsExceptionHandler implements ExceptionHandlerInterface
{
  use EmitsErrorResponses;

  /**
   * WhoopsExceptionHandler constructor.
   *
   * @inheritDoc
   */
  public function __construct(protected LoggerInterface $logger)
  {
  }

  /**
   * @inheritDoc
   */
  public function handle(Throwable $exception): void
  {
    $whoops = $this->createWhoopsRun();
    $this->logger->error($exception->getMessage());

    ob_start();
    $renderedBody = $whoops->handleException($exception);
    $bufferedBody = ob_get_clean() ?: '';
    $body = is_string($renderedBody) && $renderedBody !== '' ? $renderedBody : $bufferedBody;

    if ($body === '') {
      $body = $this->buildFallbackBody($exception);
    }

    $this->emitErrorResponse(
      body: $body,
      contentType: $this->getResponseContentType(),
      statusCode: $exception instanceof HttpException ? $exception->getStatus()->code : 500,
    );
  }

  /**
   * Builds a fresh Whoops runner for the current request context.
   *
   * @return Run
   */
  protected function createWhoopsRun(): Run
  {
    $whoops = new Run();
    $whoops->allowQuit(false);
    $whoops->pushHandler(match ($this->getResponseMode()) {
      'plain' => new PlainTextHandler(),
      'json' => new JsonResponseHandler(),
      default => new PrettyPageHandler(),
    });

    return $whoops;
  }

  /**
   * @return 'html'|'json'|'plain'
   */
  protected function getResponseMode(): string
  {
    if ($this->isCliContext()) {
      return 'plain';
    }

    return $this->isHtmlRequest() ? 'html' : 'json';
  }

  protected function getContentType(): string
  {
    return match ($this->getResponseMode()) {
      'plain' => 'text/plain',
      'json' => 'application/json',
      default => 'text/html',
    };
  }

  protected function getResponseContentType(): ContentType
  {
    return match ($this->getResponseMode()) {
      'plain' => ContentType::PLAIN,
      'json' => ContentType::JSON,
      default => ContentType::HTML,
    };
  }

  protected function isCliContext(): bool
  {
    return PHP_SAPI === 'cli' && !$this->hasActiveHttpRequestContext();
  }

  protected function isHtmlRequest(): bool
  {
    if ($this->hasActiveHttpRequestContext()) {
      return $this->resolveActiveRequest()->getMethod() === RequestMethod::GET;
    }

    return ($_SERVER['REQUEST_METHOD'] ?? '') === RequestMethod::GET->value;
  }

  protected function buildFallbackBody(Throwable $exception): string
  {
    $statusCode = $exception instanceof HttpException ? $exception->getStatus()->code : 500;
    $status = HttpStatus::fromInt($statusCode);

    return match ($this->getResponseMode()) {
      'plain' => Config::environment() === EnvironmentType::PRODUCTION
        ? $status->name
        : ($exception->getMessage() !== '' ? $exception->getMessage() : $status->name),
      'json' => (json_encode(match (Config::environment()) {
        EnvironmentType::PRODUCTION => [
          'statusCode' => $status->code,
          'message' => $status->name,
        ],
        default => [
          'statusCode' => $status->code,
          'message' => $exception->getMessage() !== '' ? $exception->getMessage() : $status->name,
          'error' => $status->name,
        ],
      }) ?: '{}'),
      default => FrameworkErrorPageRenderer::render(
        statusCode: $status->code,
        statusName: $status->name,
        heading: $exception instanceof HttpException ? $status->name : 'Unhandled exception',
        message: match (Config::environment()) {
          EnvironmentType::PRODUCTION => 'Something went wrong while processing this request.',
          default => $exception->getMessage() !== '' ? $exception->getMessage() : 'An unexpected exception was raised.',
        },
        details: Config::environment() === EnvironmentType::PRODUCTION
          ? null
          : basename($exception->getFile()) . ':' . $exception->getLine(),
      ),
    };
  }
}
