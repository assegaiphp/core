<?php

namespace Assegai\Core\Exceptions\Http;

use Assegai\Core\Http\HttpStatus;

/**
 * Class InternalServerErrorException. This class represents an internal server error exception.
 *
 * @package Assegai\Core\Exceptions\Http
 */
class InternalServerErrorException extends HttpException
{
  /**
   * InternalServerErrorException constructor.
   *
   * @param string $message The message.
   */
  public function __construct(string $message = 'Internal Server Error')
  {
    parent::__construct($message, HttpStatus::InternalServerError());
  }
}