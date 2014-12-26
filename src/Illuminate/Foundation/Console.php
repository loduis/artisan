<?php namespace Illuminate\Foundation;

use Symfony\Component\Finder\Finder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Config;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class Console
{
    private $config;

    private $app;

    private $binds = [
        'Illuminate\Contracts\Http\Kernel' => 'Illuminate\Foundation\Http\Kernel',
        'Illuminate\Contracts\Console\Kernel' => 'Illuminate\Foundation\Console\Kernel',
        'Illuminate\Contracts\Debug\ExceptionHandler' => 'Illuminate\Foundation\Exceptions\Handler'
    ];

    /**
     * Create a new Illuminate artisan instance.
     */
    public function __construct($basePath)
    {
        $this->config   = new Config($basePath);
    }

    private function config()
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

    public function scanCommands($app)
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
    /**
     * [setPaths description]
     * @param [type] $app    [description]
     * @param [type] $config [description]
     */
    public static function userPaths($app, $config)
    {
        foreach ($config->get('paths') as $key => $path) {
            $app[$key == 'path' ? $key : 'path.' . $key] = $path;
        }
    }

    public function start($app)
    {
        foreach ($this->binds as $name => $value) {
            $app->singleton($name, $value);
        }

        $status = $app->make('Illuminate\Contracts\Console\Kernel')->handle(new ArgvInput, new ConsoleOutput);

        exit($status);
    }

    public static function run($basePath)
    {
        $console = new static($basePath);

        $config  = $console->config();

        $app     = new Application($basePath);

        // Coloca los paths personalizados por el usuario

        $console->userPaths($app, $config);

        // Escanea los comando personalizado por el usuario

        $console->scanCommands($app);

        // Start console listen

        $console->start($app);
    }
}
