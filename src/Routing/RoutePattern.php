<?php

namespace Assegai\Core\Routing;

use Assegai\Core\Exceptions\Http\HttpException;

final class RoutePattern
{
  private const array CONSTRAINT_PATTERNS = [
    'int' => '/^-?\d+$/',
    'slug' => '/^[A-Za-z][A-Za-z0-9_-]*$/',
    'uuid' => '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
    'alpha' => '/^[A-Za-z]+$/',
    'alnum' => '/^[A-Za-z0-9]+$/',
    'hex' => '/^[A-Fa-f0-9]+$/',
    'ulid' => '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i',
  ];

  /**
   * @return array{constraint: string|null, name: string|null, type: 'dynamic'|'static'|'wildcard', value: string}
   * @throws HttpException
   */
  public static function parseSegment(string $segment): array
  {
    if ($segment === '*') {
      return ['constraint' => null, 'name' => null, 'type' => 'wildcard', 'value' => $segment];
    }

    if (!str_starts_with($segment, ':')) {
      return ['constraint' => null, 'name' => null, 'type' => 'static', 'value' => $segment];
    }

    [$name, $constraint] = self::parseDynamicPlaceholder(
      value: $segment,
      invalidSubject: 'route segment',
      unknownSubject: 'route constraint',
    );

    return [
      'constraint' => $constraint,
      'name' => $name,
      'type' => 'dynamic',
      'value' => $segment,
    ];
  }

  /**
   * @return array{constraint: string|null, name: string|null, type: 'dynamic'|'static'|'wildcard', value: string}
   * @throws HttpException
   */
  public static function parseHostLabel(string $label): array
  {
    if ($label === '*') {
      return ['constraint' => null, 'name' => null, 'type' => 'wildcard', 'value' => $label];
    }

    if (!str_starts_with($label, ':')) {
      return ['constraint' => null, 'name' => null, 'type' => 'static', 'value' => $label];
    }

    [$name, $constraint] = self::parseDynamicPlaceholder(
      value: $label,
      invalidSubject: 'host label',
      unknownSubject: 'host constraint',
    );

    return [
      'constraint' => $constraint,
      'name' => $name,
      'type' => 'dynamic',
      'value' => $label,
    ];
  }

  public static function matchesConstraint(string $constraint, string $value): bool
  {
    if (!isset(self::CONSTRAINT_PATTERNS[$constraint])) {
      return false;
    }

    return preg_match(self::CONSTRAINT_PATTERNS[$constraint], $value) === 1;
  }

  /**
   * @return array{0: string, 1: string|null}
   * @throws HttpException
   */
  private static function parseDynamicPlaceholder(string $value, string $invalidSubject, string $unknownSubject): array
  {
    $placeholder = substr($value, 1);
    if ($placeholder === '') {
      throw new HttpException(
        "Invalid constrained $invalidSubject '$value'. Use ':name' or ':name<constraint>'."
      );
    }

    if (preg_match('/^(?<name>[^<>]+)<(?<constraint>[A-Za-z][A-Za-z0-9_]*)>$/', $placeholder, $matches)) {
      $name = $matches['name'];
      $constraint = $matches['constraint'];
    } elseif (str_contains($placeholder, '<') || str_contains($placeholder, '>')) {
      throw new HttpException(
        "Invalid constrained $invalidSubject '$value'. Use ':name' or ':name<constraint>'."
      );
    } else {
      $name = $placeholder;
      $constraint = null;
    }

    if ($constraint !== null && !array_key_exists($constraint, self::CONSTRAINT_PATTERNS)) {
      $locationSubject = str_replace('route ', '', $invalidSubject);

      throw new HttpException("Unknown $unknownSubject '$constraint' in $locationSubject '$value'.");
    }

    return [$name, $constraint];
  }
}
