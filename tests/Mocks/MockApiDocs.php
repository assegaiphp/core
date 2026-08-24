<?php

namespace Mocks;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\HostParam;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Query;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Rendering\View;
use Assegai\Validation\Attributes\IsInt;
use Assegai\Validation\Attributes\IsNotEmpty;
use Assegai\Validation\Attributes\IsOptional;
use Assegai\Validation\Attributes\IsString;

class CreatePostDTO
{
  #[IsString]
  #[IsNotEmpty]
  public string $title;

  #[IsString]
  public string $body;
}

class SearchPostsDTO
{
  #[IsOptional]
  #[IsString]
  public ?string $search = null;

  #[IsInt]
  public int $page = 1;
}

class PostDTO
{
  public int $id;
  public string $title;
  public string $body;
}

class RecursiveNodeDTO
{
  public string $name;
  public ?RecursiveNodeDTO $next = null;
}

#[Controller('posts')]
class ApiDocsPostsController
{
  #[Post]
  public function create(#[Body] CreatePostDTO $dto): PostDTO
  {
    return new PostDTO();
  }

  #[Get]
  public function findAll(#[Query] SearchPostsDTO $query): array
  {
    return [];
  }

  #[Get(':id<int>')]
  public function findOne(int $id): PostDTO
  {
    return new PostDTO();
  }
}

#[Controller('pages')]
class ApiDocsPagesController
{
  #[Get('home')]
  public function home(): View
  {
    throw new \RuntimeException('This method is only used for OpenAPI reflection tests.');
  }
}

#[Controller('nodes')]
class ApiDocsNodesController
{
  #[Get(':id<int>')]
  public function show(int $id): RecursiveNodeDTO
  {
    return new RecursiveNodeDTO();
  }
}

#[Controller(path: 'runtime', host: ':vendor_runtime_slug<slug>.runtime.localhost')]
class ApiDocsRuntimeController
{
  #[Get('status')]
  public function status(#[HostParam('vendor_runtime_slug')] string $vendorRuntimeSlug): array
  {
    return ['vendor' => $vendorRuntimeSlug];
  }
}

#[Controller(path: 'legacy-host', host: ':tenant-id.example.com')]
class ApiDocsLegacyHostController
{
  #[Get('status')]
  public function status(#[HostParam('tenant-id')] string $tenantId): array
  {
    return ['tenant' => $tenantId];
  }
}

#[Controller(path: 'port-host', host: [':tenant:8080', 'api.:tenant:8080', 'api.:tenant<slug>:8081'])]
class ApiDocsPortHostController
{
  #[Get('status')]
  public function status(#[HostParam('tenant')] string $tenant): array
  {
    return ['tenant' => $tenant];
  }
}

#[Controller(path: 'console', host: ':tenant<slug>.console.example.com')]
class ApiDocsHostScopedConsoleController
{
  #[Get('status')]
  public function status(#[HostParam('tenant')] string $tenant): array
  {
    return ['tenant' => $tenant];
  }
}

#[Controller(path: 'child')]
class ApiDocsInheritedHostChildController
{
  #[Get('status')]
  public function status(#[HostParam('tenant')] string $tenant): array
  {
    return ['tenant' => $tenant];
  }
}

#[Module(
  controllers: [ApiDocsInheritedHostChildController::class],
)]
class ApiDocsInheritedHostChildModule
{
}

#[Module(
  controllers: [ApiDocsHostScopedConsoleController::class],
  imports: [ApiDocsInheritedHostChildModule::class],
)]
class ApiDocsInheritedHostAppModule
{
}

#[Controller('api')]
class ApiDocsSharedApiParentController
{
}

#[Controller('control')]
class ApiDocsSharedControlParentController
{
}

#[Controller('shared')]
class ApiDocsSharedController
{
  #[Get(':id<int>')]
  public function findOne(int $id): array
  {
    return ['id' => $id];
  }
}

#[Module(
  controllers: [ApiDocsSharedController::class],
)]
class ApiDocsSharedModule
{
}

#[Module(
  controllers: [ApiDocsSharedApiParentController::class],
  imports: [ApiDocsSharedModule::class],
)]
class ApiDocsSharedApiParentModule
{
}

#[Module(
  controllers: [ApiDocsSharedControlParentController::class],
  imports: [ApiDocsSharedModule::class],
)]
class ApiDocsSharedControlParentModule
{
}

#[Module(
  imports: [ApiDocsSharedApiParentModule::class, ApiDocsSharedControlParentModule::class],
)]
class ApiDocsSharedMountedAppModule
{
}

#[Controller(path: 'gateway', host: 'api.example.com')]
class ApiDocsSharedApiHostParentController
{
}

#[Controller(path: 'gateway', host: 'control.example.com')]
class ApiDocsSharedControlHostParentController
{
}

#[Controller('shared-host')]
class ApiDocsSharedHostController
{
  #[Get]
  public function index(): array
  {
    return [];
  }
}

#[Module(
  controllers: [ApiDocsSharedHostController::class],
)]
class ApiDocsSharedHostModule
{
}

#[Module(
  controllers: [ApiDocsSharedApiHostParentController::class],
  imports: [ApiDocsSharedHostModule::class],
)]
class ApiDocsSharedApiHostParentModule
{
}

#[Module(
  controllers: [ApiDocsSharedControlHostParentController::class],
  imports: [ApiDocsSharedHostModule::class],
)]
class ApiDocsSharedControlHostParentModule
{
}

#[Module(
  imports: [ApiDocsSharedApiHostParentModule::class, ApiDocsSharedControlHostParentModule::class],
)]
class ApiDocsSharedHostMountedAppModule
{
}

#[Module(
  controllers: [
    ApiDocsPostsController::class,
    ApiDocsPagesController::class,
    ApiDocsNodesController::class,
    ApiDocsRuntimeController::class,
    ApiDocsLegacyHostController::class,
    ApiDocsPortHostController::class,
  ],
)]
class ApiDocsAppModule
{
}
