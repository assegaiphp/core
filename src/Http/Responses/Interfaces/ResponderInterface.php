<?php

namespace Assegai\Core\Http\Responses\Interfaces;

use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Http\HttpStatusCode;
use ReflectionException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * The Responder interface.
 *
 * @package Assegai\Core\Http\Responses\Interfaces
 */
interface ResponderInterface
{
  /**
   * * Send a response to the client and exit the script.
   * *
   * * @param mixed $response The response to send.
   * * @param HttpStatusCode|int|null $code The response code to send.
   * * @return never
   * * @throws ReflectionException
   * * @throws RenderingException
   * * @throws LoaderError
   * * @throws RuntimeError
   * * @throws SyntaxError
 */
  public function respond(mixed $response, HttpStatusCode|int|null $code = null): never;
}