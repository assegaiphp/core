<?php

namespace Mocks;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Core\Interfaces\MiddlewareInterface;
use Assegai\Core\Routing\Route;

class MiddlewareTrace
{
  public static array $events = [];

  public static function reset(): void
  {
    self::$events = [];
  }
}

#[Injectable]
class FirstTraceMiddleware implements MiddlewareInterface
{
  public function use(Request $request, Response $response, callable $next): void
  {
    MiddlewareTrace::$events[] = 'first:before';
    $next();
    MiddlewareTrace::$events[] = 'first:after';
  }
}

#[Injectable]
class SecondTraceMiddleware implements MiddlewareInterface
{
  public function use(Request $request, Response $response, callable $next): void
  {
    MiddlewareTrace::$events[] = 'second:before';
    $next();
    MiddlewareTrace::$events[] = 'second:after';
  }
}

#[Injectable]
class PostOnlyMiddleware implements MiddlewareInterface
{
  public function use(Request $request, Response $response, callable $next): void
  {
    MiddlewareTrace::$events[] = 'post:before';
    $next();
    MiddlewareTrace::$events[] = 'post:after';
  }
}

#[Injectable]
class StopRequestMiddleware implements MiddlewareInterface
{
  public function use(Request $request, Response $response, callable $next): void
  {
    MiddlewareTrace::$events[] = 'stop';
    $response->setBody('blocked');
  }
}

#[Controller('middleware')]
class MiddlewareTestController
{
  #[Get]
  public function index(): string
  {
    MiddlewareTrace::$events[] = 'controller:index';

    return 'index';
  }

  #[Get(':id<int>')]
  public function show(int $id): string
  {
    MiddlewareTrace::$events[] = 'controller:show';

    return "show-$id";
  }

  #[Post]
  public function create(): string
  {
    MiddlewareTrace::$events[] = 'controller:create';

    return 'create';
  }
}

#[Controller('middleware-short-circuit')]
class MiddlewareShortCircuitController
{
  #[Get]
  public function index(): string
  {
    MiddlewareTrace::$events[] = 'controller:short';

    return 'should-not-run';
  }
}

#[Module(
  controllers: [MiddlewareTestController::class],
)]
class MiddlewareFeatureModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
    $consumer
      ->apply(FirstTraceMiddleware::class, SecondTraceMiddleware::class)
      ->exclude(new Route('/middleware/:id<int>', RequestMethod::GET))
      ->forRoutes(MiddlewareTestController::class);

    $consumer
      ->apply(PostOnlyMiddleware::class)
      ->forRoutes(new Route('/middleware', RequestMethod::POST));
  }
}

#[Module(
  controllers: [MiddlewareShortCircuitController::class],
)]
class MiddlewareShortCircuitModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
    $consumer
      ->apply(StopRequestMiddleware::class)
      ->forRoutes(MiddlewareShortCircuitController::class);
  }
}

#[Module(
  controllers: [NestedRootController::class],
  imports: [MiddlewareFeatureModule::class],
)]
class MiddlewareAppModule
{
}

#[Module(
  controllers: [NestedRootController::class],
  imports: [MiddlewareShortCircuitModule::class],
)]
class MiddlewareShortCircuitAppModule
{
}
