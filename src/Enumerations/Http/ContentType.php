<?php

namespace Assegai\Core\Enumerations\Http;

/**
 * Represents the content type of the request.
 *
 * @package Assegai\Core\Enumerations\Http
 */
enum ContentType: string
{
  case PLAIN = 'text/plain';
  case PLAIN_UTF8 = 'text/plain; charset=UTF-8';
  case PLAIN_UTF16 = 'text/plain; charset=UTF-16';
  case PLAIN_UTF32 = 'text/plain; charset=UTF-32';
  case HTML = 'text/html';
  case HTML_UTF8 = 'text/html; charset=UTF-8';
  case HTML_UTF16 = 'text/html; charset=UTF-16';
  case HTML_UTF32 = 'text/html; charset=UTF-32';
  case FORM_DATA = 'multipart/form-data; charset=UTF-32';
  case JSON = 'application/json';
  case XML = 'application/xml';
  case WOFF = 'application/font-woff';
  case JWT = 'application/jwt';
  case FORM_URL_ENCODED = 'application/x-www-form-urlencoded';

  /**
   * Checks if the content type is multipart form.
   *
   * @return bool True if the content type is a form; otherwise, false.
   */
  public static function isMultipartForm(): bool
  {
    return str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
  }

  /**
   * Checks if the content type is url encoded form.
   *
   * @return bool True if the content type is a url encoded form; otherwise, false.
   */
  public static function isURLEncodedForm(): bool
  {
    return str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/x-www-form-urlencoded');
  }
}