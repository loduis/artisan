<?php

namespace Artisan;

use Illuminate\Log\LogServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application as BaseApplication;

class Application extends BaseApplication
{
    /**
     * Default paths for the laravel.
     *
     * @var array
     */
    private $paths = [
        'path'           => 'app',
        'path.lang'      => 'resources/lang',
        'path.config'    => 'config',
        'path.public'    => 'public',
        'path.storage'   => 'storage',
        'path.database'  => 'database',
        'path.bootstrap' => 'bootstrap',
        'path.resources' => 'resources',
        'path.bootstrap' => 'bootstrap',
        'path.commands'  => 'app/Console/Commands',
    ];

    /**
     * Register all of the base service providers.
     *
     * @return void
     */
    protected function registerBaseServiceProviders(): void
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new FilesystemServiceProvider($this));
    }

    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer(): void
    {
        foreach ($this->paths as $key => $path) {
            $this->setPath($key, $this->basePath . '/' . $path);
        }
        $this->setPath('path.base', $this->basePath);
    }

    public function setPath($key, $value): void
    {
        $this->paths[$key] = $value;
        $this->instance($key, $value);
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path Optionally, a path to append to the bootstrap path
     * @return string
     */
    public function bootstrapPath($path = ''): string
    {
        return $this->paths['path.bootstrap'].($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path Optionally, a path to append to the config path
     * @return string
     */
    public function configPath($path = ''): string
    {
        return $this->paths['path.config'].($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the path to the database directory.
     *
     * @param  string  $path Optionally, a path to append to the database path
     * @return string
     */
    public function databasePath($path = ''): string
    {
        return $this->paths['path.database'].($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the path to the language files.
     *
     * @return string
     */
    public function langPath(): string
    {
        return $this->paths['path.lang'];
    }

    /**
     * Get the path to the public / web directory.
     *
     * @return string
     */
    public function publicPath(): string
    {
        return $this->options['path.public'];
    }

    /**
     * Get the path to the storage directory.
     *
     * @return string
     */
    public function storagePath(): string
    {
        return $this->paths['path.storage'];
    }

    /**
     * Get the path to the resources directory.
     *
     * @param  string  $path
     * @return string
     */
    public function resourcePath($path = ''): string
    {
        return $this->paths['path.resources'].($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}
