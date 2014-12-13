<?php namespace Illuminate\Foundation;

use Illuminate\Filesystem\Filesystem;

class Artisan
{
    public static function config()
    {
        $basePath   = getcwd();
        $configFile = $basePath . '/composer.json';
        if ($config = static::loadFromComposer($configFile)) {
            $config = static::parseConfig($basePath, $config);
            print_r($config);
        }
    }

    private static function loadFromComposer($configFile)
    {
        $files = new Filesystem();
        if ($files->exists($configFile)) {
            $content        = $files->get($configFile);
            $config         = json_decode($content, true);
            if (isset($config['laravel'])) {
                return $config['laravel'];
            }
        }
    }

    private function getPath($basePath, $path)
    {
        return realpath($basePath . DIRECTORY_SEPARATOR . $path);
    }

    private static function parseConfig($basePath, $config)
    {
        $apps    = [];
        $commons = [
            'base' => $basePath
        ];
        foreach ($config as $name => $path) {
            // Es un directorio de aplicacion
            if (is_array($path)) {
                $apps[$name] = static::getAppPaths($basePath, $path);
            } else {
                $commons[$name] = static::getPath($basePath, $path);
            }
        }

        return static::appendCommonsToApps($apps, $commons);
    }

    private static function getAppPaths($basePath, $app)
    {
        if (isset($app['base'])) {
            $basePath .= DIRECTORY_SEPARATOR . $app['base'];
            unset($app['base']);
        }
        foreach ($app as & $path) {
            $path = static::getPath($basePath, $path);
        }
        unset($path);

        return $app;
    }

    private static function appendCommonsToApps($apps, $commons)
    {
        foreach ($apps as $name => & $config) {
            $config = array_merge($config, $commons);
        }
        unset($config);

        return $apps;
    }
}
