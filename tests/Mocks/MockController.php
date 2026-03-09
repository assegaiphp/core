<?php

namespace Mocks;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Attributes\Param;

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
