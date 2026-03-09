<?php

namespace Mocks;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Modules\Module;

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
