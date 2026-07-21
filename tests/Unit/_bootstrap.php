<?php

$sessionDirectory = sys_get_temp_dir() . '/assegaiphp-core-session-tests';

if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0777, true);
}

session_save_path($sessionDirectory);
