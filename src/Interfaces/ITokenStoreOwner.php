<?php

namespace Assegai\Core\Interfaces;

/**
 * Interface ITokenStoreOwner. This interface is implemented by classes that own token stores.
 *
 * @package Assegai\Core\Interfaces
 */
interface ITokenStoreOwner
{
  /**
   * Checks if a token exists in the store.
   *
   * @param string $entryId The token to check.
   * @return bool True if the token exists, false otherwise.
   */
  public function has(string $entryId): bool;

  /**
   * Gets a token from the store.
   *
   * @param string $entryId The token to get.
   * @return mixed The token.
   */
  public function get(string $entryId): mixed;

  /**
   * Adds a token to the store.
   *
   * @param string $entryId The token to add.
   * @param mixed $token The token to add.
   * @return int The token ID.
   */
  public function add(string $entryId, mixed $token): int;

  /**
   * Removes a token from the store.
   *
   * @param int $tokenId The token ID to remove.
   * @return int|false The number of tokens removed, or false if the token was not found.
   */
  public function remove($tokenId): int|false;
}