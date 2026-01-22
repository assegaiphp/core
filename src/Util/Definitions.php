<?php

namespace Assegai\Core\Util;

defined('DEFAULT_STORAGE_PATH')     || define('DEFAULT_STORAGE_PATH',     trim(shell_exec('pwd')) . '/uploads');
defined('DEFAULT_FIELD_NAME_SIZE')  || define('DEFAULT_FIELD_NAME_SIZE',  100);
defined('DEFAULT_FIELD_SIZE')       || define('DEFAULT_FIELD_SIZE',       '1MB');
defined('DEFAULT_MAX_FILE_SIZE')    || define('DEFAULT_MAX_FILE_SIZE',    5000);
defined('DEFAULT_MAX_FILE_FIELDS')  || define('DEFAULT_MAX_FILE_FIELDS',  5000);
defined('DEFAULT_MAX_PARTS')        || define('DEFAULT_MAX_PARTS',        5000);
defined('DEFAULT_MAX_HEADER_PAIRS') || define('DEFAULT_MAX_HEADER_PAIRS', 2000);
