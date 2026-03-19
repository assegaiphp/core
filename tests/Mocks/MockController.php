<?php

namespace Mocks;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\HostParam;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Header;
use Assegai\Core\Attributes\Http\HttpCode;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Redirect;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\Res;
use Assegai\Core\Attributes\ResponseStatus;
use Assegai\Core\Attributes\UseGuards;
use Assegai\Core\Attributes\UseInterceptors;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Assegai\Core\Interfaces\ICanActivate;
use Assegai\Core\Interfaces\IExecutionContext;

#[Controller('test')]
class MockController
{
  #[Post]
  public function create()
  {
    return 'create';
  }

  #[Get]
  public function findAll()
  {
    return 'This action returns all users';
  }

  #[Get(':id')]
  public function findOne(int $id)
  {
    return "This action returns a #$id users";
  }

  #[Patch(':id')]
  public function update(int $id)
  {
    return "This action updates a #$id user";
  }

  #[Delete(':id')]
  public function remove(int $id)
  {
    return "This action removes a #$id user";
  }
}

#[Controller('handler-wildcards')]
class WildcardHandlerController
{
  #[Get]
  public function index(): string
  {
    return 'exact-handler';
  }

  #[Get('*')]
  public function catchAll(): string
  {
    return 'wildcard-handler';
  }
}

#[Controller('controller-wildcards')]
class ExactWildcardController
{
  #[Get]
  public function index(): string
  {
    return 'exact-controller';
  }
}

#[Controller('controller-wildcards/*')]
class CatchAllWildcardController
{
  #[Get('*')]
  public function index(): string
  {
    return 'wildcard-controller';
  }
}

#[Controller('/')]
class NestedRootController
{
  #[Get]
  public function index(): string
  {
    return 'root';
  }
}

#[Controller('api')]
class NestedApiController
{
  #[Get]
  public function index(): string
  {
    return 'api';
  }
}

#[Controller('features')]
class NestedFeaturesController
{
  #[Get(':id')]
  public function findOne(int $id): string
  {
    return "feature-$id";
  }
}

#[Controller('posts')]
class NestedPostsController
{
  #[Get(':id')]
  public function findOne(int $id): string
  {
    return "post-$id";
  }
}

#[Module(
  controllers: [NestedFeaturesController::class],
)]
class NestedFeaturesModule
{
}

#[Module(
  controllers: [NestedPostsController::class],
)]
class NestedPostsModule
{
}

#[Module(
  controllers: [NestedApiController::class],
  imports: [NestedFeaturesModule::class, NestedPostsModule::class],
)]
class NestedApiModule
{
}

#[Module(
  controllers: [NestedRootController::class],
  imports: [NestedApiModule::class],
)]
class NestedAppModule
{
}

#[Controller('users')]
class ConstrainedUsersController
{
  #[Get('me')]
  public function me(): string
  {
    return 'me';
  }

  #[Get(':id<int>')]
  public function findById(#[Param('id')] int $id): string
  {
    return "id-$id";
  }

  #[Get(':username<slug>')]
  public function findByUsername(#[Param('username')] string $username): string
  {
    return "username-$username";
  }

  #[Get(':token<uuid>')]
  public function findByToken(#[Param('token')] string $token): string
  {
    return "uuid-$token";
  }

  #[Get(':legacy')]
  public function legacy(#[Param('legacy')] string $legacy): string
  {
    return "legacy-$legacy";
  }
}

#[Controller('tokens')]
class UuidOnlyController
{
  #[Get(':token<uuid>')]
  public function findOne(#[Param('token')] string $token): string
  {
    return "token-$token";
  }
}

#[Controller('constraints')]
class BuiltinConstraintController
{
  #[Get('alpha/:value<alpha>')]
  public function alpha(#[Param('value')] string $value): string
  {
    return "alpha-$value";
  }

  #[Get('alnum/:value<alnum>')]
  public function alnum(#[Param('value')] string $value): string
  {
    return "alnum-$value";
  }

  #[Get('hex/:value<hex>')]
  public function hex(#[Param('value')] string $value): string
  {
    return "hex-$value";
  }

  #[Get('ulid/:value<ulid>')]
  public function ulid(#[Param('value')] string $value): string
  {
    return "ulid-$value";
  }
}

#[Controller('constraints/flexible')]
class FlexibleConstraintController
{
  #[Get('int/:value<int>')]
  public function intValue(#[Param('value')] mixed $value): string
  {
    return get_debug_type($value) . "-$value";
  }

  #[Get('slug/:value<slug>')]
  public function slugValue(#[Param('value')] mixed $value): string
  {
    return get_debug_type($value) . "-$value";
  }
}

#[Controller('broken')]
class InvalidConstraintController
{
  #[Get(':id<int')]
  public function broken(#[Param('id')] string $id): string
  {
    return $id;
  }
}

#[Controller('unknown-constraint')]
class UnknownConstraintController
{
  #[Get(':id<money>')]
  public function broken(#[Param('id')] string $id): string
  {
    return $id;
  }
}

#[Controller('strict')]
class MismatchedConstraintController
{
  #[Get(':id<int>')]
  public function broken(#[Param('id')] string $id): string
  {
    return $id;
  }
}

#[Controller(path: 'dashboard')]
class PublicDashboardController
{
  #[Get]
  public function index(): string
  {
    return 'public-dashboard';
  }
}

#[Controller(path: 'dashboard', host: ':account.example.com')]
class TenantDashboardController
{
  #[Get]
  public function index(#[HostParam('account')] string $account): string
  {
    return "tenant-$account";
  }
}

#[Controller(path: 'dashboard', host: 'admin.example.com')]
class AdminDashboardController
{
  #[Get]
  public function index(): string
  {
    return 'admin-dashboard';
  }
}

#[Controller(path: 'reports', host: ['ops.example.com', 'support.example.com'])]
class MultiHostReportsController
{
  #[Get]
  public function index(): string
  {
    return 'reports-dashboard';
  }
}

#[Controller(path: 'response-metadata')]
class ResponseMetadataController
{
  #[HttpCode(202)]
  #[Get('accepted-before')]
  public function acceptedBefore(): string
  {
    return 'accepted-before';
  }

  #[Get('accepted-after')]
  #[HttpCode(202)]
  public function acceptedAfter(): string
  {
    return 'accepted-after';
  }

  #[Get('no-content')]
  #[ResponseStatus(204)]
  public function noContent(): array
  {
    return [];
  }

  #[Header('X-Export-Version', '1')]
  #[Get('headers')]
  public function headers(): string
  {
    return 'headers';
  }

  #[Header('X-First', 'yes')]
  #[Get('header-first')]
  public function headerFirst(): string
  {
    return 'header-first';
  }

  #[Get('redirect')]
  #[Redirect('/sign-in')]
  public function redirect(): string
  {
    return 'redirect';
  }

  #[Get('manual-status')]
  #[HttpCode(202)]
  public function manualStatus(#[Res] Response $response): string
  {
    $response->setStatus(418);

    return 'manual-status';
  }

  #[Get('manual-header')]
  #[Header('X-Route', 'controller')]
  public function manualHeader(#[Res] Response $response): string
  {
    $response->setHeader('X-Route', 'handler');

    return 'manual-header';
  }

  #[Get('manual-redirect')]
  #[Redirect('/route-default')]
  public function manualRedirect(#[Res] Response $response): Response
  {
    return $response->redirect('/manual-target', 303);
  }
}

#[Injectable]
class RequestAwareGuard implements ICanActivate
{
  public function __construct(private readonly Request $request)
  {
  }

  public function canActivate(IExecutionContext $context): bool
  {
    return trim($this->request->getPath(), '/') === 'pipeline/request-aware';
  }
}

#[Injectable]
class RequestAwareInterceptor implements IAssegaiInterceptor
{
  public function __construct(private readonly Request $request)
  {
  }

  public function intercept(ExecutionContext $context): ?callable
  {
    $path = trim($this->request->getPath(), '/');

    return function (ExecutionContext $context) use ($path) {
      $context->switchToHttp()->getResponse()->setHeader('X-Request-Path', $path);

      return $context;
    };
  }
}

#[Controller(path: 'pipeline')]
class RequestAwarePipelineController
{
  #[Get('request-aware')]
  #[UseGuards(RequestAwareGuard::class)]
  #[UseInterceptors(RequestAwareInterceptor::class)]
  public function requestAware(): string
  {
    return 'request-aware';
  }
}

#[Module(
  controllers: [ConstrainedUsersController::class, UuidOnlyController::class],
)]
class ConstrainedUsersModule
{
}

#[Module(
  controllers: [BuiltinConstraintController::class, FlexibleConstraintController::class],
)]
class AdditionalBuiltinConstraintsModule
{
}

#[Module(
  controllers: [InvalidConstraintController::class],
)]
class InvalidConstraintModule
{
}

#[Module(
  controllers: [UnknownConstraintController::class],
)]
class UnknownConstraintModule
{
}

#[Module(
  controllers: [MismatchedConstraintController::class],
)]
class MismatchedConstraintModule
{
}

#[Module(
  controllers: [
    PublicDashboardController::class,
    TenantDashboardController::class,
    AdminDashboardController::class,
    MultiHostReportsController::class,
  ],
)]
class HostRoutingAppModule
{
}

#[Module(
  controllers: [ResponseMetadataController::class],
)]
class ResponseMetadataAppModule
{
}

#[Module(
  controllers: [RequestAwarePipelineController::class],
)]
class RequestAwarePipelineAppModule
{
}

#[Module(
  controllers: [MockController::class],
)]
class LegacyAppModule
{
}

#[Module(
  controllers: [WildcardHandlerController::class],
)]
class WildcardHandlerAppModule
{
}

#[Module(
  controllers: [ExactWildcardController::class, CatchAllWildcardController::class],
)]
class WildcardControllerAppModule
{
}

#[Module(
  controllers: [NestedRootController::class],
  imports: [ConstrainedUsersModule::class, AdditionalBuiltinConstraintsModule::class],
)]
class ConstrainedRoutingAppModule
{
}

#[Module(
  controllers: [NestedRootController::class],
  imports: [InvalidConstraintModule::class],
)]
class InvalidConstraintAppModule
{
}

#[Module(
  controllers: [NestedRootController::class],
  imports: [UnknownConstraintModule::class],
)]
class UnknownConstraintAppModule
{
}

#[Module(
  controllers: [NestedRootController::class],
  imports: [MismatchedConstraintModule::class],
)]
class MismatchedConstraintAppModule
{
}
