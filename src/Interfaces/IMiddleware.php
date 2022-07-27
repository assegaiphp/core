<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;

interface IMiddleware
{
  public function use(Request $request, Response $response, callable $next): void;
}