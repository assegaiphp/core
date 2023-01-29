<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;

interface IRenderable
{
  public function render(Request $request): Response;
}