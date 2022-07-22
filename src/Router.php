<?php

namespace Assegai\Core;

use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\Queries;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Http\Request;
use Assegai\Core\Responses\Response;
use Assegai\Core\Util\Validator;
use Assegai\Core\Attributes\Controller;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final class Router
{
  private static ?Router $instance = null;
  protected Injector $injector;

  private final function __construct()
  {
    $this->injector = Injector::getInstance();
  }

  /**
   * @return Router
   */
  public static function getInstance(): Router
  {
    if (!self::$instance)
    {
      self::$instance = new Router();
    }

    return self::$instance;
  }

  /**
   * @param string $url
   * @return Request
   */
  public function route(string $url): Request
  {
    // TODO: Implement route()
    return Request::getInstance();
  }

  /**
   * @param Request $request
   * @param array $controllerTokensList
   * @return object
   * @throws ContainerException
   * @throws NotFoundException
   * @throws ReflectionException
   * @throws HttpException
   */
  public function getActivatedController(Request $request, array $controllerTokensList): object
  {
    $activatedController = null;

    foreach ($controllerTokensList as $reflectionController)
    {
      if ($this->canActivateController($reflectionController))
      {
        $activatedController = $this->activateController($reflectionController);
      }
    }

    if (!$activatedController)
    {
      throw new NotFoundException(path: $request->getPath());
    }

    return $activatedController;
  }

  /**
   * @param ReflectionClass $reflectionController
   * @return bool
   * @throws HttpException
   */
  private function canActivateController(ReflectionClass $reflectionController): bool
  {
    $request = Request::getInstance();
    $path = str_starts_with($request->getPath(), '/') ? $request->getPath() : '/' . $request->getPath();

    $attributes = $reflectionController->getAttributes(Controller::class);

    if (empty($attributes))
    {
      throw new HttpException("Invalid controller: " . $reflectionController->getName());
    }

    foreach ($attributes as $attribute)
    {
      $instance = $attribute->newInstance();
      $prefix = str_replace('/^\/\//', '', '/' . $instance->path);

      if ($path === $prefix)
      {
        return true;
      }

      if (str_starts_with($path, $prefix))
      {
        if (!empty($path) && $prefix === '/')
        {
          continue;
        }
        return true;
      }
    }

    return false;
  }

  /**
   * @param ReflectionClass $reflectionController
   * @return object Returns an instance of the activated controller
   * @throws Exceptions\Container\ContainerException
   * @throws ReflectionException
   */
  private function activateController(ReflectionClass $reflectionController): object
  {
    $constructor = $reflectionController->getConstructor();
    $constructorParams = $constructor->getParameters();

    $dependencies = [];

    foreach ($constructorParams as $param)
    {
      $dependencies[] = $this->injector->resolve($param->getType()->getName());
    }

    return $reflectionController->newInstanceArgs($dependencies);
  }

  /**
   * @param object $controller
   * @return ReflectionMethod[] Returns a list of handlers belonging to the given controller
   */
  public function getControllerHandlers(object $controller): array
  {
    $handlers = [];
    $reflectionClass = new ReflectionClass($controller);
    $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach($reflectionMethods as $reflectionMethod)
    {
      if ($this->isValidHandler($reflectionMethod))
      {
        $handlers[] = $reflectionMethod;
      }
    }

    return $handlers;
  }

  /**
   * @param ReflectionMethod[] $handlers
   * @param object $controller
   * @param Request $request
   * @return ReflectionMethod|null
   */
  public function getActivatedHandler(array $handlers, object $controller, Request $request): ?ReflectionMethod
  {
    foreach ($handlers as $handler)
    {
      if ($this->canActivateHandler(handler: $handler, controller: $controller, request: $request))
      {
        return $handler;
      }
    }

    return null;
  }

  /**
   * @param ReflectionMethod $method
   * @return bool
   */
  public function isValidHandler(ReflectionMethod $method): bool
  {
    $attributes = $method->getAttributes();

    $foundRequestMapperAttribute = false;
    foreach ($attributes as $attribute)
    {
      if (Validator::isValidRequestMapperAttribute($attribute))
      {
        $foundRequestMapperAttribute = true;
      }
    }

    return $foundRequestMapperAttribute;
  }

  /**
   * @param ReflectionMethod $handler
   * @param object $controller
   * @param Request $request
   * @return bool
   */
  public function canActivateHandler(ReflectionMethod $handler, object $controller, Request $request): bool
  {
    $path = $request->getPath();
//    $path = str_starts_with($request->getPath(), '/') ? substr($request->getPath(), 1): $request->getPath();
    $controllerPrefix = $this->getControllerPrefix(controller: $controller);
    $handlerPath = $this->getHandlerPath(handler: $handler);

    $pattern = $this->getPathMatchingPattern(path: "$controllerPrefix/$handlerPath");
    $request->extractParams(path: $path, pattern: $pattern);

    $attributes = $handler->getAttributes();

    if (empty($attributes))
    {
      return false;
    }

    $requestMapperClassFound = false;
    /** @var ReflectionAttribute $attribute */
    foreach ($attributes as $ignored)
    {
      if ($this->isPathMatch(pattern: $pattern, path: $path))
      {
        $requestMapperClassFound = true;
      }
    }

    return $requestMapperClassFound;
  }

  /**
   * @param Request $request
   * @param object $controller
   * @return Response
   * @throws ContainerException
   * @throws ReflectionException|NotFoundException
   */
  public function handleRequest(Request $request, object $controller): Response
  {
    $handlers = $this->getControllerHandlers(controller: $controller);
    $activatedHandler = $this->getActivatedHandler(handlers: $handlers, controller: $controller, request: $request);

    if (!$activatedHandler)
    {
      throw new NotFoundException($request->getPath());
    }

    $params = $activatedHandler->getParameters();
    $dependencies = [];

    foreach ($params as $param)
    {
      if ($param->getType()->isBuiltin())
      {
        $paramAttributes = $param->getAttributes();

        foreach ($paramAttributes as $paramAttribute)
        {
          switch ($paramAttribute->getName())
          {
            case Param::class:
              $paramAttributeArgs = $paramAttribute->getArguments();
              if (empty($paramAttributeArgs))
              {
                $dependencies[$param->getPosition()] = json_encode($request->getParams());
              }
              else
              {
                $dependencies[$param->getPosition()] = $request->getParams()[$param->getPosition()] ?? null;
              }
              break;

            case Queries::class:
              break;
          }
        }
      }
      else
      {
        $dependencies[$param->getPosition()] = $this->injector->resolve($param->getType()->getName());
      }
    }

    $result = $activatedHandler->invokeArgs($controller, $dependencies);

    if ($result instanceof Response)
    {
      return $result;
    }

    $response = Response::getInstance();
    $response->setBody($result);

    return $response;
  }

  /**
   * @param object $controller
   * @return string
   */
  private function getControllerPrefix(object $controller): string
  {
    $reflectionController = new ReflectionClass($controller);
    $attributes = $reflectionController->getAttributes(Controller::class);

    foreach ($attributes as $attribute)
    {
      $instance = $attribute->newInstance();
      return $instance->path;
    }

    return '';
  }

  /**
   * @param ReflectionMethod $handler
   * @return string
   */
  private function getHandlerPath(ReflectionMethod $handler): string
  {
    $attributes = $handler->getAttributes();
    foreach ($attributes as $attribute)
    {
      return $attribute->newInstance()->path;
    }

    return '';
  }

  /**
   * @param string $path
   * @return string
   */
  private function getPathMatchingPattern(string $path): string
  {
    return preg_replace('/:\w+/', '(\w+)', $path);
  }

  /**
   * @param string $pattern
   * @param string $path
   * @return bool
   */
  private function isPathMatch(string $pattern, string $path): bool
  {
    $pattern = str_replace('/', '\/', $pattern);
    return preg_match_all("/$pattern/", $path) !== false;
  }
}