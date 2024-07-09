<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;

/**
 * Interface IMiddleware. This interface is for middleware.
 *
 * @package Assegai\Core\Interfaces
 */
interface MiddlewareInterface
{
  /**
   * Use the middleware.
   *
   * @param Request $request
   * @param Response $response
   * @param callable $next
   * @return void
   */
  public function use(Request $request, Response $response, callable $next): void;
}