<?php

$command_path = app_path('commands');
foreach (File::files($command_path) as $command) {

    File::requireOnce($command);
    $command_name = str_replace($command_path . DIRECTORY_SEPARATOR, '', $command);
    $command_name = str_replace('/', '\\', $command_name);
    $command_name = basename($command_name, '.php');

    Artisan::add(new $command_name);
}

