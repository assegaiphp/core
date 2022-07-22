<?php

namespace Assegai\Core\Enumerations\Http;

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
}