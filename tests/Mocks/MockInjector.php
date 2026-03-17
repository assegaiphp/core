<?php

namespace Mocks;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;

#[Injectable]
class FrameworkAwareService
{
  public function __construct(
    public Request $request,
    public Response $response,
    public ProjectConfig $projectConfig,
  )
  {
  }
}

#[Module(
  providers: [FrameworkAwareService::class],
  controllers: [],
  imports: [],
)]
class FrameworkAwareAppModule
{
}
