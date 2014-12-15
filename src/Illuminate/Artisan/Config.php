<?php namespace Illuminate\Artisan;

use Illuminate\Support\Collection;

class Config
{
    /**
     * keep the laravel configuration
     *
     * @type string
     */
    const LARAVEL_CONFIG_FILE = 'laravel.json';

    /**
     * Path where laravel is install
     *
     * @var string
     */
    private $basePath;

    /**
     * keep the laravel config
     *
     * @var Illuminate\Support
     */
    private $data;

    /**
     * Create a new Illuminate Artisan config instance.
     */
    public function __construct($basePath)
    {
        $this->basePath   = $basePath;
        $config           = $this->loadFromFile(self::LARAVEL_CONFIG_FILE);
        $this->data       = new Collection($config);
    }

    private function loadFromFile($filename)
    {
        $file = $this->basePath . '/' . $filename;
        $config = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);

            $config = json_decode($content, true);

            if (!is_array($config)) {
                $config = [];
            }
        }

        return $config;
    }

    public function applications()
    {
        $apps = $this->data->get('apps');
        if (is_array($apps)) {
            $apps = array_map(
                function ($app) {
                    if (!isset($app['paths'])) {
                        $app['paths'] = [];
                    }
                    $app['paths']['base'] = $this->basePath;

                    return new Collection($app);
                },
                $apps
            );
        }

        return new Collection($apps);
    }
}
