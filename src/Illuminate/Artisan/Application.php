<?php namespace Illuminate\Artisan;

class Application
{
    private $config;

    /**
     * Create a new Illuminate artisan instance.
     */
    public function __construct($basePath)
    {
        $this->config   = new Config($basePath);
    }

    private function getAppNameFromFirstArgument()
    {
        if (count($_SERVER['argv']) >= 2) {
            $name     =  $_SERVER['argv'][1];
            unset($_SERVER['argv'][1]);

            $_SERVER['argv'] = array_values($_SERVER['argv']);
            $_SERVER['argc'] = count($_SERVER['argv']);

            return $name;
        }
    }

    public function run()
    {
        $apps    = $this->config->applications();
        if ($apps->isEmpty()) {
            die('There are not valid laravel configuration' . PHP_EOL);
        } elseif ($apps->count() > 1) {
            $name = $this->getAppNameFromFirstArgument();
            if (is_null($name)) {
                $config = $apps->first();
            } else {
                $config = $apps->where('name', $name);
                if ($config->isEmpty()) {
                    die(
                        'This is not valid laravel application: ' . $name .
                        PHP_EOL
                    );
                } else {
                    $config = $config->first();
                }
            }
        } else {
            $config = $apps->first();
        }

        return $config;
    }

    /**
     * Instance of the Artisan application
     *
     */
    public static function make($basePath)
    {
        return new static($basePath);
    }
}
