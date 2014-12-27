<?php namespace Illuminate\Foundation;

use ReflectionClass;
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
        'Illuminate\Contracts\Http\Kernel'            => 'Illuminate\Foundation\Http\Kernel',
        'Illuminate\Contracts\Console\Kernel'         => 'Illuminate\Foundation\Console\Kernel',
        'Illuminate\Contracts\Debug\ExceptionHandler' => 'Illuminate\Foundation\Exceptions\Handler'
    ];

    private $commands = [
        'command.console.make' => 'Illuminate\Foundation\Console\ConsoleMakeCommand'
    ];

    /**
     * Create a new Illuminate artisan instance.
     */
    public function __construct($basePath)
    {
        $this->app    = new Application($basePath);
        $this->config = new Config($basePath);
    }

    public function registerDefaultCommands()
    {
        $this->registerCustomCommands($this->commands);
    }

    public function registerCustomCommands($commands)
    {
        $commandNames = [];
        foreach ($commands as $commandName => $commandClass) {
            if (!starts_with($commandName, 'command.')) {
                $name = "command.$commandName";
            }
            $this->app->singleton($commandName, function ($app) use ($commandClass) {
                $params = [];
                $class       = new ReflectionClass($commandClass);
                $constructor = $class->getConstructor();
                foreach ($constructor->getParameters() as $parameter) {
                    $paramClass = $parameter->getClass()->name;
                    if ($paramClass) {
                        $value = $this->app->make($paramClass);
                        $params[$parameter->getPosition()] = $value;
                    }
                }

                return $class->newInstanceArgs($params);
            });

            $commandNames[] = $commandName;
        }

        $this->registerCommands($commandNames);
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

    public function scanCommands()
    {
        $files = Finder::create()
                            ->in($this->app['path.commands'])
                            ->name('*.php');
        $commands = [];
        foreach ($files as $file) {
            $commands[]   = $this->registerScanCommand($file);
        }

        $this->registerCommands($commands);
    }

    private function registerScanCommand($file)
    {
        $commandClass = $this->getCommandClassName($file);
        $command      = new $commandClass();
        $name         = 'command.' . $command->getName();

        $this->app->singleton($name, function () use ($command) {
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

    private function registerCommands($commands)
    {
        // To register the commands with Artisan, we will grab each of the arguments
        // passed into the method and listen for Artisan "start" event which will
        // give us the Artisan console instance which we will give commands to.

        $events = $this->app['events'];

        $events->listen('artisan.start', function ($artisan) use ($commands) {
            $artisan->resolveCommands($commands);
        });
    }

    /**
     * Extract the application name from first argument
     *
     * @return string|null
     */
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
    public function userPaths($config)
    {
        foreach ($config->get('paths') as $key => $path) {
            $key = $key == 'path' ? $key : 'path.' . $key;
            $this->app[$key] = $path;
        }
    }

    public function start()
    {
        foreach ($this->binds as $name => $value) {
            $this->app->singleton($name, $value);
        }

        $status = $this->app->make('Illuminate\Contracts\Console\Kernel')->handle(new ArgvInput, new ConsoleOutput);

        exit($status);
    }

    public static function run($basePath)
    {

        $console = new static($basePath);

        $config  = $console->config();

        // Coloca los paths personalizados por el usuario

        $console->userPaths($config);

        $console->registerDefaultCommands();

        if ($config->has('commands')) {
            $console->registerCustomCommands($config->get('commands'));
        }

        // Escanea los comando personalizado por el usuario

        $console->scanCommands();

        // Start console listen

        $console->start();
    }
}
