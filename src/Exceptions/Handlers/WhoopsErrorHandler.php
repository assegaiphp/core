<?php

namespace Assegai\Core\Exceptions\Handlers;

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
    /**
     * The Whoops error handler.
     *
     * @var Run $whoops
     */
    protected Run $whoops;

    /**
     * WhoopsExceptionHandler constructor.
     */
    public function __construct(protected LoggerInterface $logger)
    {
        try {
            $this->whoops = new Run();
            $pageHandler = match (true) {
                $_SERVER['REQUEST_METHOD'] === 'GET' => new PrettyPageHandler(),
                php_sapi_name() === 'cli' => new PlainTextHandler(),
                default => new JsonResponseHandler(),
            };
            $this->whoops->pushHandler($pageHandler);
            $this->whoops->register();
        } catch (Throwable $throwable) {
            if (!headers_sent()) {
                header('Content-Type: text/html');
            }
            echo $throwable->getMessage();
            exit(1);
        }
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
        if (!headers_sent()) {
            $contentType = match (true) {
                $_SERVER['REQUEST_METHOD'] === 'GET' => 'text/html',
                php_sapi_name() === 'cli' => 'text/plain',
                default => 'application/json',
            };
            header("Content-Type: $contentType");

            if ($error instanceof HttpException) {
                $this->whoops->sendHttpCode($error->getCode());
            }
        }

        echo $this->whoops->handleException($error);
    }
}