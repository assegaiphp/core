<?php

namespace Mocks;

use Assegai\Core\Attributes\Controller;
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

#[Module(
  controllers: [ApiDocsPostsController::class, ApiDocsPagesController::class],
)]
class ApiDocsAppModule
{
}
