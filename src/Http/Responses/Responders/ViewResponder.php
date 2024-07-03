<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Http\InternalServerErrorException;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Rendering\Engines\ViewEngine;
use Assegai\Core\Rendering\View;

/**
 * ViewResponder class. This class is used by the response object to render a view.
 *
 * @package Assegai\Core\Http\Responses\Responders
 */
class ViewResponder implements ResponderInterface
{
  /**
   * Constructs a ViewResponder.
   *
   * @param ViewEngine $viewEngine The ViewEngine instance.
   */
  public function __construct(protected ViewEngine $viewEngine)
  {
  }

  /**
   * @inheritDoc
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): never
  {
    if ($response instanceof Response) {
      $responseBody = $response->getBody();
      $response->setContentType(ContentType::HTML);

      if ($responseBody instanceof View) {
        $this->viewEngine->load($responseBody)->render();
      }

      throw new InternalServerErrorException("Response body is not a View instance.");
    }

    if ($response instanceof View) {
      $this->viewEngine->load($response)->render();
    }

    throw new InternalServerErrorException("Response is not a View instance.");
  }
}