<?php namespace Illuminate\Foundation\Console;

use Illuminate\Support\Collection;

class Config
{
    /**
     * keep the laravel configuration
     *
     * @type string
     */
    const LARAVEL_CONFIG_FILE = 'composer.json';

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
    private $data;

    private $psr4 = [];

    /**
     * Create a new Illuminate Artisan config instance.
     */
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $config         = $this->loadFromFile($this->filePath());
        $this->data     = new Collection($config);
    }

    private function loadFromFile($file)
    {
        $config = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);

            $config = json_decode($content, true);
            $psr4   = (array) data_get($config, 'autoload.psr-4');
            $config = isset($config['extra']) ? $config['extra'] : [];
            $config = isset($config['laravel']) ? $config['laravel'] : [];
            if (!is_array($config)) {
                $config = [];
            }
            $this->psr4 = $psr4;
        }

        return $config;
    }

    public function filePath()
    {
        return $this->basePath . '/' . static::LARAVEL_CONFIG_FILE;
    }

    private function getNamespace($app)
    {
        $namespace = isset($app['namespace']) ? $app['namespace'] : $app['name'];
        $namespace = ucfirst($namespace);
        $namespace = strtok($namespace, '\\') . '\\';

        return $namespace;
    }

    private function getPaths($app, $appPath)
    {
        $appPath = rtrim($appPath, '/');
        $paths           = isset($app['paths']) ? $app['paths'] : [];
        unset ($paths['path']);
        $newPaths = [];
        foreach ($paths as $key => $path) {
            $newPaths[$key] = strpos($path, '..') !== false ? $path : ($appPath . '/' . $path);
        }
        $newPaths['path'] = $appPath;

        return $newPaths;
    }


    public function applications()
    {
        $apps = $this->data->get('apps');
        if (is_array($apps)) {
            $apps = array_map(
                function ($app) {
                    $namespace        = $this->getNamespace($app);
                    $app['namespace'] = $namespace;
                    $app['paths']     = $this->getPaths($app, $this->psr4[$namespace]);

                    return new Collection($app);
                },
                $apps
            );
        }

        return new Collection($apps);
    }
}
