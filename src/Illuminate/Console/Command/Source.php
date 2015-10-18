<?php

namespace Illuminate\Console\Command;

use Closure;

class Source
{
    /**
     * Get the command class for the file
     *
     * @param  string|\Symfony\Component\Finder\SplFileInfo $file
     *
     * @return string
     */
    public static function getCommand($file)
    {
        $tokens              = token_get_all($file->getContents());
        $count               = count($tokens);
        $start               = 0;
        $namespace           = '';
        $commandName         = '';
        $commandNameCallback = function ($start, $tokens, $count) {
            if (($varname = static::getTokenOfType(T_VARIABLE, $start, $tokens, $count)) &&
                ($varname == '$name' || $varname == '$signature')
            ) {
                return $start;
            }
        };

        if (static::findTokenOfType(T_NAMESPACE, $start, $tokens, $count)) {
            $namespace    = '\\' . static::getTokenOfType(T_STRING, $start, $tokens, $count, '\\');
        }

        if (!static::findTokenOfType(T_CLASS, $start, $tokens, $count)) {
            return [];
        }

        $className    = static::getTokenOfType(T_STRING, $start, $tokens, $count);
        $commandClass =  $namespace. '\\' . $className;

        if (!static::findTokenOfType(T_PROTECTED, $start, $tokens, $count, $commandNameCallback)) {
            return [];
        }

        $commandName = static::getTokenOfType(T_CONSTANT_ENCAPSED_STRING, $start, $tokens, $count);
        $commandName = trim($commandName, "'");
        $commandName = trim($commandName, '"');

        return [$commandName => $commandClass];
    }

    private static function findTokenOfType($type, &$start, $tokens, $count, Closure $callback = null)
    {
        for ($i = $start + 1; $i < $count; ++$i) {
            $token               = static::getToken($i, $tokens);

            list($number, ) = $token;

            if ($number == $type && (!($callback instanceof Closure) || ($i = $callback($i, $tokens, $count)))) {
                return $start = $i;
            }
        }

        return false;
    }

    private static function getTokenOfType($type, & $start, $tokens, $count, $joinWith = '')
    {
        $namespace   = [];
        $storedStart = $start;
        for ($i = $start + 1; $i < $count; ++$i) {
            $token               = static::getToken($i, $tokens);

            list($number, $text) = $token;

            if ($number == $type) {
                $namespace[] = $text;
                $storedStart = $storedStart;
                continue;
            }

            if (($number == T_WHITESPACE && count($namespace) > 0) || $text == '{' || $text == ';') {
                $start = $storedStart;
                break;
            }
        }

        return implode($joinWith, $namespace);
    }

    private static function getToken($i, $tokens)
    {
        $token = $tokens[$i];
        if (!is_array($token)) {
            $token = [null, $token];
        }

        return $token;
    }
}
