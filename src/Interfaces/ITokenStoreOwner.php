<?php

namespace Assegai\Core\Interfaces;

interface ITokenStoreOwner
{
  public function has(string $entryId): bool;

  public function get(string $entryId): mixed;

  public function add(string $entryId, mixed $token): int;

  public function remove($tokenId): int|false;
}