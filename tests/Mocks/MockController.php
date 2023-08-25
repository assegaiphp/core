<?php

namespace Mocks;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;

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