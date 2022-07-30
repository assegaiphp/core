<?php

namespace Assegai\Core\Util;

defined('DEFAULT_STORAGE_PATH')     or define('DEFAULT_STORAGE_PATH',     trim(shell_exec('pwd')) . '/uploads');
defined('DEFAULT_FIELD_NAME_SIZE')  or define('DEFAULT_FIELD_NAME_SIZE',  100);
defined('DEFAULT_FIELD_SIZE')       or define('DEFAULT_FIELD_SIZE',       '1MB');
defined('DEFAULT_MAX_FILE_SIZE')    or define('DEFAULT_MAX_FILE_SIZE',    5000);
defined('DEFAULT_MAX_FILE_FIELDS')  or define('DEFAULT_MAX_FILE_FIELDS',  5000);
defined('DEFAULT_MAX_PARTS')        or define('DEFAULT_MAX_PARTS',        5000);
defined('DEFAULT_MAX_HEADER_PAIRS') or define('DEFAULT_MAX_HEADER_PAIRS', 2000);