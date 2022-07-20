<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Http\Request;
use Assegai\Core\Responses\Response;

/**
 * Specifies methods to obtain request and response objects.
 */
interface IHttpArgumentsHost
{
  /**
   * Returns the in-flight `request` object.
   * @return Request The in-flight `request` object.
   */
  public function getRequest(): Request;

  /**
   * Returns the in-flight `response` object
   * @return Response The in-flight `response` object
   */
  public function getResponse(): Response;

  public function getNext(): mixed;

  public function getArgs(): array;
}