<?php

namespace Assegai\Core\Interfaces;

interface ITokenStoreOwner
{
  public function has(string $tokenId): bool;

  public function get(string $tokenId): mixed;

  public function add(string $tokenId, mixed $token): int;

  public function remove($tokenId): int|false;
}