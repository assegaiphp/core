<?php

namespace Assegai\Core\Routing;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Head;
use Assegai\Core\Attributes\Http\Options;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Put;
use Assegai\Core\Attributes\UseGuards;
use Assegai\Core\Attributes\UseInterceptors;
use Assegai\Core\Consumers\GuardsConsumer;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\ForbiddenException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Interceptors\InterceptorsConsumer;
use Assegai\Core\Util\Validator;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use stdClass;

final class Router
{
  private static ?Router $instance = null;
  private Injector $injector;
  private GuardsConsumer $guardsConsumer;
  private InterceptorsConsumer $interceptorsConsumer;

  private final function __construct()
  {
    $this->injector = Injector::getInstance();
    $this->interceptorsConsumer = InterceptorsConsumer::getInstance();
    $this->guardsConsumer = GuardsConsumer::getInstance();
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
   * @return Request
   */
  public function route(): Request
  {
    return Request::getInstance();
  }

  /**
   * @param Request $request
   * @param ReflectionClass[] $controllerTokensList
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
   * @throws ContainerException
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
        $this->parseHandlerAttributes($handler);
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
    foreach ($attributes as $attribute)
    {
      if ($this->isPathMatch(pattern: $pattern, path: $path))
      {
        switch($request->getMethod())
        {
          case RequestMethod::OPTIONS:
            if ($attribute->getName() === Options::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::GET:
            if ($attribute->getName() === Get::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::POST:
            if ($attribute->getName() === Post::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::PUT:
            if ($attribute->getName() === Put::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::PATCH:
            if ($attribute->getName() === Patch::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::DELETE:
            if ($attribute->getName() === Delete::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::HEAD:
            if ($attribute->getName() === Head::class)
            {
              $requestMapperClassFound = true;
            }
        }
      }
    }

    return $requestMapperClassFound;
  }

  /**
   * @param Request $request
   * @param object $controller
   * @return Response
   * @throws ContainerException
   * @throws ReflectionException|NotFoundException|EntryNotFoundException
   * @throws HttpException
   */
  public function handleRequest(Request $request, object $controller): Response
  {
    $handlers = $this->getControllerHandlers(controller: $controller);
    $activatedHandler = $this->getActivatedHandler(handlers: $handlers, controller: $controller, request: $request);

    if (!$activatedHandler)
    {
      throw new NotFoundException($request->getPath());
    }

    $controllerReflection = new ReflectionClass($controller);
    $context = $this->createContext(class: $controllerReflection, handler: $activatedHandler);

    # Consume controller guards
    $useGuardsAttributes = $controllerReflection->getAttributes(UseGuards::class);
    if ($useGuardsAttributes)
    {
      /** @var UseGuards $controllerUseGuardsInstance */
      $controllerUseGuardsInstance = $useGuardsAttributes[0]->newInstance();

      if (! $this->guardsConsumer->canActivate(guards: $controllerUseGuardsInstance->guards, context: $context) )
      {
        throw new ForbiddenException();
      }
    }

    # Consume controller interceptors
    $controllerInterceptorCallHandlers = [];
    $useInterceptorsAttributes = $controllerReflection->getAttributes(UseInterceptors::class);

    if ($useInterceptorsAttributes)
    {
      /** @var UseInterceptors $controllerUseInterceptorsInstance */
      $controllerUseInterceptorsInstance = $useInterceptorsAttributes[0]->newInstance();

      $controllerInterceptorCallHandlers =
        $this->interceptorsConsumer
          ->intercept(
            interceptors: $controllerUseInterceptorsInstance->interceptorsList,
            context: $context
          );
    }

    # Consume handler guards
    $useGuardsAttributes = $activatedHandler->getAttributes(UseGuards::class);

    if ($useGuardsAttributes)
    {
      /** @var UseGuards $handlerUseGuardsAttribute */
      $handlerUseGuardsAttribute = $useGuardsAttributes[0]->newInstance();

      if (! $this->guardsConsumer->canActivate(guards: $handlerUseGuardsAttribute->guards, context: $context) )
      {
        throw new ForbiddenException();
      }
    }

    # Consume handler interceptors
    $handlerInterceptorCallHandlers = [];
    $useInterceptorsAttributes = $activatedHandler->getAttributes(UseInterceptors::class);

    if ($useInterceptorsAttributes)
    {
      /** @var UseInterceptors $handlerUseInterceptorsInstance */
      $handlerUseInterceptorsInstance = $useInterceptorsAttributes[0]->newInstance();

      $handlerInterceptorCallHandlers =
        $this->interceptorsConsumer
          ->intercept(
            interceptors: $handlerUseInterceptorsInstance->interceptorsList,
            context: $context
          );
    }

    # Resolve handler parameters
    $params = $activatedHandler->getParameters();
    $dependencies = [];

    foreach ($params as $param)
    {
      $paramTypeName = $param->getType()->getName();
      $isStandardClassType = is_subclass_of($paramTypeName, stdClass::class) || $paramTypeName === 'stdClass';
      $dependencies[$param->getPosition()] = match(true) {
        $param->getType()->isBuiltin(),
        $isStandardClassType => $this->injector->resolveBuiltIn($param, $request),
        default => $this->injector->resolve($param->getType()->getName())
      };
    }

    try
    {
      $result = $activatedHandler->invokeArgs($controller, $dependencies);
    }
    catch (Exception $e)
    {
      throw new HttpException(message: $e->getMessage());
    }

    if ($result instanceof Response)
    {
      return $result;
    }

    $context->switchToHttp()->getResponse()->setBody($result);

    # Run handler Interceptors
    /** @var callable $handler */
    foreach ($handlerInterceptorCallHandlers as $handler)
    {
      /** @var ExecutionContext $context */
      $context = $handler($context);
    }

    # Run controller Interceptors
    /** @var callable $handler */
    foreach ($controllerInterceptorCallHandlers as $handler)
    {
      /** @var ExecutionContext $context */
      $context = $handler($context);
    }

    return $context->switchToHttp()->getResponse();
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
    if (str_ends_with($path, '/'))
    {
      $path = preg_replace('/\/$/', '', $path);
    }

    $path = str_replace('*', '.*', $path);

    return preg_replace(pattern: '/(\/?):\w+/', replacement: '$1(\w+)', subject: $path);
  }

  /**
   * @param string $pattern
   * @param string $path
   * @return bool
   */
  private function isPathMatch(string $pattern, string $path): bool
  {
    $path = preg_replace('/^\//', '', $path);
    $pattern = str_replace('/', '\/', $pattern);
    $result = preg_match_all("/$pattern/", $path);

    return boolval($result) === true;
  }

  /**
   * @param ReflectionMethod $activatedHandler
   * @return void
   */
  private function parseHandlerAttributes(ReflectionMethod $activatedHandler): void
  {
    $reflectionAttributes = $activatedHandler->getAttributes();
    foreach ($reflectionAttributes as $attribute)
    {
      $attribute->newInstance();
    }
  }

  /**
   * @param ReflectionClass $class
   * @param ReflectionMethod $handler
   * @return ExecutionContext
   */
  private function createContext(ReflectionClass $class, ReflectionMethod $handler): ExecutionContext
  {
    return new ExecutionContext(class: $class, handler: $handler);
  }
}