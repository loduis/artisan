<?php

namespace Illuminate\Console;

use OutOfBoundsException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class Config
{
    /**
     * Path where laravel is install
     *
     * @var string
     */
    private $basePath;

    /**
     * keep the laravel config
     *
     * @var \Illuminate\Support\Collection
     */
    private $items;

    /**
     * Composer psr4 info
     *
     * @var array
     */
    private $psr4 = [];

    /**
     * Create a new Illuminate Artisan config instance.
     */
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $config         = $this->loadFromFile($this->filePath());
        $this->items    = new Collection($config);
    }

    public function applications()
    {
        $apps = $this->items->get('applications');
        if (is_array($apps)) {
            $apps = $this->transformToCollections($apps);
        }

        return new Collection($apps);
    }

    /**
     * Get the file path of config
     *
     * @return string
     */
    public function filePath()
    {
        return $this->basePath . '/composer.json';
    }

    private function application($name, $app)
    {
        $app['namespace'] = $this->getNamespace($app, $name);
        $app['paths']     = $this->getPaths($app);
        $app['commands']  = $this->getCommands($app);

        return $app;
    }

    /**
     * Load config from file
     *
     * @param  string
     * @return array
     */
    private function loadFromFile($file)
    {
        $config = [];
        if (file_exists($file)) {
            $content    = file_get_contents($file);
            $composer   = json_decode($content, true);
            $config     = (array) Arr::get($composer, 'extra.laravel');
            $this->psr4 = (array) Arr::get($composer, 'autoload.psr-4');
        }

        return $config;
    }

    /**
     * Get the namespace for application from namespace or name
     *
     * @param  array
     * @return string
     */
    private function getNamespace($app, $default)
    {
        $namespace = Arr::get($app, 'namespace', $default);
        $namespace = ucfirst($namespace) . '\\';

        return $namespace;
    }

    /**
     * Get application paths
     *
     * @param  array $app
     * @return array
     */
    private function getPaths($app)
    {
        $appPath  = $this->getPathFromNamespace($app['namespace']);
        $paths    = array_merge((array) data_get($this->items, 'shared.paths'), (array) Arr::get($app, 'paths'));
        $newPaths = [];

        foreach ($paths as $key => $path) {
            $newPaths[$key] = Str::contains($path, '.') ? $path : ($appPath . '/' . $path);
        }

        $newPaths['path'] = $appPath;

        return $newPaths;
    }
    /**
     * Get application commands
     *
     * @param  array $app
     * @return array
     */
    private function getCommands($app)
    {
        $appCommands    = (array) Arr::get($app, 'commands');
        $sharedCommands = (array) data_get($this->items, 'shared.commands');

        return array_merge($sharedCommands, $appCommands);
    }

    private function getPathFromNamespace($namespace)
    {
        if (!Arr::has($this->psr4, $namespace)) {
            throw new OutOfBoundsException('Not can find namespace: ' . $namespace . ' in composer psr4');
        }

        return rtrim($this->psr4[$namespace]);
    }

    private function transformToCollections(array $apps)
    {
        array_walk($apps, function (&$app, $name) {
            $app = new Collection($this->application($name, $app));
        });

        return $apps;
    }
}
