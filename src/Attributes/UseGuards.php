<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\GuardException;
use Assegai\Core\Exceptions\Http\ForbiddenException;
use Assegai\Core\Interfaces\ICanActivate;
use Attribute;

/**
 * An attribute that specifies which Guards to use when determining whether an endpoint can be activated.
 */
#[Attribute]
class UseGuards
{
  public readonly array $guards;

  /**
   * @param ICanActivate[]|string[]|ICanActivate|string $guard
   * @throws GuardException
   */
  public function __construct(
    protected readonly array|ICanActivate|string $guard,
    public readonly string $exceptionClassName = ForbiddenException::class
  )
  {
    $this->guards = $this->normalizeGuards($this->guard);
  }

  /**
   * Returns a normalized flat list of guard definitions.
   *
   * @param ICanActivate[]|string[]|ICanActivate|string $guardsList The list of guards.
   * @return array<int, ICanActivate|string> Returns an array of guard definitions.
   * @throws GuardException
   */
  private function normalizeGuards(array|ICanActivate|string $guardsList): array
  {
    $guards = [];

    if (!is_array($guardsList)) {
      return [$guardsList];
    }

    foreach ($guardsList as $guard) {
      if (is_array($guard)) {
        $guards = [...$guards, ...$this->normalizeGuards($guard)];
      } elseif ($guard instanceof ICanActivate || is_string($guard)) {
        $guards[] = $guard;
      } else {
        throw new GuardException(message: 'Invalid guard type');
      }
    }

    return $guards;
  }
}
