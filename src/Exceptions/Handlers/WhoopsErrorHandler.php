<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\Concerns\EmitsErrorResponses;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Interfaces\ErrorHandlerInterface;
use ErrorException;
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
class WhoopsErrorHandler implements ErrorHandlerInterface
{
    use EmitsErrorResponses;

    /**
     * WhoopsExceptionHandler constructor.
     */
    public function __construct(protected LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(int $errno, string $errstr, string $errfile, int $errline): void
    {
        $this->handleError(new ErrorException($errstr, 0, $errno, $errfile, $errline));
    }

    /**
     * @inheritDoc
     */
    public function handleError(Throwable $error): void
    {
        $whoops = $this->createWhoopsRun();

        ob_start();
        $renderedBody = $whoops->handleException($error);
        $bufferedBody = ob_get_clean() ?: '';

        $this->emitErrorResponse(
            body: is_string($renderedBody) && $renderedBody !== '' ? $renderedBody : $bufferedBody,
            contentType: $this->getResponseContentType(),
            statusCode: $error instanceof HttpException ? $error->getStatus()->code : 500,
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
        return PHP_SAPI === 'cli';
    }

    protected function isHtmlRequest(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
    }
}
