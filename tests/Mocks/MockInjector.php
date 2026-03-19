<?php

namespace Mocks;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Session;

#[Injectable]
class FrameworkAwareService
{
  public function __construct(
    public Request $request,
    public Response $response,
    public ProjectConfig $projectConfig,
    public Session $session,
  )
  {
  }
}

#[Injectable]
class FrameworkAwareContractsService
{
  public function __construct(
    public RequestInterface $request,
    public ResponseInterface $response,
    public ResponseEmitterInterface $emitter,
    public ResponderInterface $responder,
  )
  {
  }
}

#[Module(
  providers: [FrameworkAwareService::class, FrameworkAwareContractsService::class],
  controllers: [],
  imports: [],
)]
class FrameworkAwareAppModule
{
}
