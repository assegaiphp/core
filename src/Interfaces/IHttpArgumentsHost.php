<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;

/**
 * Specifies methods to obtain request and response objects.
 *
 * @package Assegai\Core\Interfaces
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

  /**
   * Returns the next callable in the chain.
   * @return mixed The next callable in the chain.
   */
  public function getNext(): mixed;

  /**
   * Returns the arguments passed to the controller.
   * @return array<int|string, mixed> The arguments passed to the controller.
   */
  public function getArgs(): array;
}