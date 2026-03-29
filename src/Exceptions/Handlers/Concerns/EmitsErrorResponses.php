<?php

namespace Assegai\Core\Exceptions\Handlers\Concerns;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Runtimes\RuntimeContext;

trait EmitsErrorResponses
{
  protected function emitErrorResponse(string $body, ContentType $contentType, int $statusCode): void
  {
    $response = Response::current();
    $response->reset();
    $response->setStatus($statusCode);
    $response->setContentType($contentType);
    $response->setHeader('Content-Type', $contentType->value);
    $response->setBody($body);

    $emitter = RuntimeContext::get(ResponseEmitterInterface::class)
      ?? Injector::getInstance()->get(ResponseEmitterInterface::class);

    if (!$emitter instanceof ResponseEmitterInterface) {
      $emitter = new PhpResponseEmitter();
    }

    $emitter->emit($body, $response);
  }

  protected function shouldRenderHtmlErrorPage(): bool
  {
    $request = $this->resolveActiveRequest();

    $accept = strtolower($request->header('Accept'));
    $contentType = strtolower($request->header('Content-Type'));

    return $request->getMethod() === RequestMethod::GET
      && !str_contains($accept, 'application/json')
      && !str_contains($contentType, 'application/json');
  }

  protected function hasActiveHttpRequestContext(): bool
  {
    $request = RuntimeContext::get(RequestInterface::class)
      ?? Injector::getInstance()->get(RequestInterface::class);

    return $request instanceof RequestInterface;
  }

  protected function resolveActiveRequest(): RequestInterface
  {
    $request = RuntimeContext::get(RequestInterface::class)
      ?? Injector::getInstance()->get(RequestInterface::class);

    if ($request instanceof RequestInterface) {
      return $request;
    }

    return Request::current();
  }

  protected function statusName(int $statusCode): string
  {
    return HttpStatus::fromInt($statusCode)->name;
  }
}
