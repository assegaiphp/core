<?php

namespace Mocks;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Enumerations\Scope;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Core\Interfaces\ConfiguresInjectorInterface;
use Assegai\Core\Interfaces\ParameterResolverInterface;
use Assegai\Core\Session;
use Attribute;
use ReflectionParameter;

#[Injectable]
class FrameworkAwareService
{
  public function __construct(
    public Request $request,
    public Response $response,
    public ProjectConfig $projectConfig,
    public Session $session,
  )
  {
  }
}

#[Injectable]
class FrameworkAwareContractsService
{
  public function __construct(
    public RequestInterface $request,
    public ResponseInterface $response,
    public ResponseEmitterInterface $emitter,
    public ResponderInterface $responder,
  )
  {
  }
}

#[Injectable]
class RequestCapturingService
{
  public function __construct(
    public Request $request,
  )
  {
  }
}

#[Injectable(options: ['scope' => Scope::REQUEST, 'durable' => false])]
class ExplicitRequestScopedService
{
  public function __construct(
    public Request $request,
  )
  {
  }
}

class AttributeResolvedValue
{
  public function __construct(
    public string $value,
  ) {
  }
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ResolveAttributeValue
{
  public function __construct(
    private readonly string $value = 'resolved-by-attribute',
  ) {
  }

  public function resolveParameterValue(): AttributeResolvedValue
  {
    return new AttributeResolvedValue($this->value);
  }
}

#[Injectable]
class AttributeResolvedService
{
  public function __construct(
    #[ResolveAttributeValue('attribute-seam')]
    public AttributeResolvedValue $value,
  ) {
  }
}

class ResolverResolvedValue
{
  public function __construct(
    public string $value,
  ) {
  }
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ResolveWithRegisteredResolver
{
}

class MockParameterResolver implements ParameterResolverInterface
{
  public function supports(ReflectionParameter $parameter, Injector $injector): bool
  {
    return !empty($parameter->getAttributes(ResolveWithRegisteredResolver::class));
  }

  public function resolve(ReflectionParameter $parameter, Injector $injector): mixed
  {
    return new ResolverResolvedValue('resolved-by-registry');
  }
}

#[Injectable]
class ResolverResolvedService
{
  public function __construct(
    #[ResolveWithRegisteredResolver]
    public ResolverResolvedValue $value,
  ) {
  }
}

#[Module(
  providers: [
    ResolverResolvedService::class,
  ],
  controllers: [],
  imports: [],
)]
class ResolverBridgeModule implements AssegaiModuleInterface, ConfiguresInjectorInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }

  public function configureInjector(Injector $injector): void
  {
    $injector->registerParameterResolver(new MockParameterResolver());
  }
}

#[Module(
  providers: [
    ResolverResolvedService::class,
  ],
  controllers: [],
  imports: [],
)]
class ResolverOnlyBridgeModule implements ConfiguresInjectorInterface
{
  public function configureInjector(Injector $injector): void
  {
    $injector->registerParameterResolver(new MockParameterResolver());
  }
}

#[Module(
  providers: [],
  controllers: [],
  imports: [
    ResolverBridgeModule::class,
  ],
)]
class ResolverAwareAppModule
{
}

#[Module(
  providers: [],
  controllers: [],
  imports: [
    ResolverOnlyBridgeModule::class,
  ],
)]
class ResolverOnlyAwareAppModule
{
}

#[Module(
  providers: [
    FrameworkAwareService::class,
    FrameworkAwareContractsService::class,
    RequestCapturingService::class,
    ExplicitRequestScopedService::class,
    AttributeResolvedService::class,
  ],
  controllers: [],
  imports: [],
)]
class FrameworkAwareAppModule
{
}
