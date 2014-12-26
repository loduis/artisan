<?php namespace Illuminate\Artisan;

use Symfony\Component\Finder\Finder;

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

    public function config()
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

    public function scan($app)
    {
        $files = Finder::create()
                            ->in($app['path.commands'])
                            ->name('*.php');
        $commands = [];
        foreach ($files as $file) {
            $commands[]   = $this->registerCommand($app, $file);
        }

        $this->registerScanCommands($app, $commands);
    }

    private function registerCommand($app, $file)
    {
        $commandClass = $this->getCommandClassName($file);
        $command      = new $commandClass();
        $name         = 'command.' . $command->getName();

        $app->singleton($name, function () use ($command) {
            return $command;
        });

        return $name;
    }

    private function getCommandClassName($file)
    {
        require_once $file->getRealPath();

        $currentClass = get_declared_classes();

        return end($currentClass);
    }

    private function registerScanCommands($app, $commands)
    {
        // To register the commands with Artisan, we will grab each of the arguments
        // passed into the method and listen for Artisan "start" event which will
        // give us the Artisan console instance which we will give commands to.

        $events = $app['events'];

        $events->listen('artisan.start', function ($artisan) use ($commands) {
            $artisan->resolveCommands($commands);
        });
    }
}
