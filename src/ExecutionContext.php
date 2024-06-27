<?php

namespace Assegai\Core;

use Assegai\Core\Enumerations\Http\ContextType;
use Assegai\Core\Interfaces\IExecutionContext;
use ReflectionClass;
use ReflectionMethod;

/**
 * Represents the context in which a controller method is executed.
 *
 * @package Assegai\Core
 */
class ExecutionContext extends ArgumentsHost implements IExecutionContext
{
  /**
   * ExecutionContext constructor.
   *
   * @param ReflectionClass|string $class The controller class.
   * @param ReflectionMethod $handler The handler method.
   * @param ContextType $contextType The context type.
   */
  public function __construct(
    protected ReflectionClass|string $class,
    protected ReflectionMethod       $handler,
    ContextType                      $contextType = ContextType::HTTP,
  )
  {
    parent::__construct(contextType: $contextType);
  }

  /**
   * Returns the type of the controller class which the current handler belongs to.
   *
   * @return string The controller class.
   */
  public function getClass(): string
  {
    if ($this->class instanceof ReflectionClass) {
      return $this->class->getName();
    }

    return $this->class;
  }

  /**
   * Returns a reference to the handler (method) that will be invoked next in the
   * request pipeline.
   *
   * @return callable|ReflectionMethod The handler method.
   */
  public function getHandler(): callable|ReflectionMethod
  {
    return $this->handler;
  }
}