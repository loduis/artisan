<?php

namespace Artisan\Console;

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
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->items    = new Collection($this->load());
    }

    /**
     * Get applications from composer file.
     *
     * @return \Illuminate\Support\Collection
     */
    public function applications(): Collection
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
    public function filePath(): string
    {
        return $this->basePath . '/composer.json';
    }

    /**
     * Set required options on application
     *
     * @param  string $name
     * @param  array $app
     * @return array
     */
    private function requiredOptions($name, $app): iterable
    {
        $app['namespace'] = $this->getNamespace($app, $name);
        $app['paths']     = $this->getPaths($app);
        $app['commands']  = $this->getCommands($app);

        return $app;
    }

    /**
     * Load config from composer file
     *
     * @return array
     */
    private function load(): iterable
    {
        $config = [];
        if (file_exists($file = $this->filePath())) {
            $content    = file_get_contents($file);
            $composer   = json_decode($content, true);
            $config     = (array) Arr::get($composer, 'extra.laravel');
            $this->psr4 = (array) Arr::get($composer, 'autoload.psr-4');
            if (!Arr::has($config, 'applications') && $this->psr4) {
                $applications = [];
                foreach ($this->psr4 as $namespace => $path) {
                    $name = strtolower(rtrim($namespace, '\\'));
                    $applications[$name] = [];
                }
                $config['applications'] = $applications;
            }
        }

        return $config;
    }

    /**
     * Get the namespace for application from namespace or name
     *
     * @param  array $app
     * @param  string $default
     * @return string
     */
    private function getNamespace(array $app, $default): string
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
    private function getPaths($app): iterable
    {
        $appPath  = $this->getPathFromNamespace($app['namespace']);
        $paths    = array_merge((array) data_get($this->items, 'shared.paths'), (array) Arr::get($app, 'paths'));
        $newPaths = [];

        foreach ($paths as $key => $path) {
            $newPaths[$key] = Str::contains($path, '.') ? $path : ($appPath . $path);
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
    private function getCommands(iterable $app): iterable
    {
        $appCommands    = (array) Arr::get($app, 'commands');
        $sharedCommands = (array) data_get($this->items, 'shared.commands');

        return array_merge($sharedCommands, $appCommands);
    }

    /**
     * Get path from namespace.
     *
     * @param  string $namespace
     * @return string
     */
    private function getPathFromNamespace(string $namespace): string
    {
        if (!Arr::has($this->psr4, $namespace)) {
            throw new OutOfBoundsException('Not can find namespace: ' . $namespace . ' in composer psr4');
        }

        return rtrim($this->psr4[$namespace]);
    }

    /**
     * Transfrom array to collection object
     *
     * @param  array  $apps
     * @return \Illuminate\Support\Collection
     */
    private function transformToCollections(iterable $apps): iterable
    {
        array_walk($apps, function (&$app, $name) {
            $app = new Collection($this->requiredOptions($name, $app));
        });

        return $apps;
    }
}
