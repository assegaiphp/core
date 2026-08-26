<?php

namespace Assegai\Core\Queues;

use Assegai\Core\Injector;
use Assegai\Core\Interfaces\ParameterResolverInterface;
use Assegai\Core\Queues\Attributes\InjectQueue;
use ReflectionParameter;

final readonly class QueueParameterResolver implements ParameterResolverInterface
{
  public function __construct(private QueueFactory $factory)
  {
  }

  public function supports(ReflectionParameter $parameter, Injector $injector): bool
  {
    return $parameter->getAttributes(InjectQueue::class) !== [];
  }

  public function resolve(ReflectionParameter $parameter, Injector $injector): mixed
  {
    /** @var InjectQueue $attribute */
    $attribute = $parameter->getAttributes(InjectQueue::class)[0]->newInstance();

    return $this->factory->connection($attribute->path);
  }
}
